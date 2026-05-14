import express from "express";
import { createServer as createViteServer } from "vite";
import { google } from "googleapis";
import cookieParser from "cookie-parser";
import path from "path";
import { fileURLToPath } from "url";
import dotenv from "dotenv";

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
const PORT = 3000;

app.use(express.json());
app.use(cookieParser());

const oauth2Client = new google.auth.OAuth2(
  process.env.GOOGLE_CLIENT_ID || "placeholder",
  process.env.GOOGLE_CLIENT_SECRET || "placeholder",
  `${process.env.APP_URL || "http://localhost:3000"}/auth/callback`
);

if (!process.env.GOOGLE_CLIENT_ID || !process.env.GOOGLE_CLIENT_SECRET) {
  console.warn("⚠️ GOOGLE_CLIENT_ID or GOOGLE_CLIENT_SECRET is missing. OAuth will not work.");
}

const SCOPES = ["https://www.googleapis.com/auth/calendar"];

// --- Helper Functions ---

async function getCalendar(tokens: any) {
  oauth2Client.setCredentials(tokens);
  return google.calendar({ version: "v3", auth: oauth2Client });
}

async function checkAvailability(calendar: any, startTime: Date, durationHours: number = 2) {
  const endTime = new Date(startTime.getTime() + durationHours * 60 * 60 * 1000);
  
  const response = await calendar.events.list({
    calendarId: "primary",
    timeMin: startTime.toISOString(),
    timeMax: endTime.toISOString(),
    singleEvents: true,
  });

  return response.data.items.length === 0;
}

async function findAlternativeSlots(calendar: any, requestedTime: Date) {
  const alternatives = [];
  // Check slots: +1h, +2h, +3h, -1h, -2h, and same time next day
  const hourOffsets = [1, 2, 3, -1, -2, 24, 25, 26];

  for (const offset of hourOffsets) {
    const testTime = new Date(requestedTime.getTime() + offset * 60 * 60 * 1000);
    
    // Ensure it's in the future and within salon hours (11 AM - 8 PM)
    const hour = testTime.getHours();
    if (testTime > new Date() && hour >= 11 && hour < 20) {
      try {
        const isAvailable = await checkAvailability(calendar, testTime);
        if (isAvailable) {
          // Format as a readable string for the AI to present
          alternatives.push(testTime.toLocaleString('en-US', { 
            weekday: 'long', 
            month: 'long', 
            day: 'numeric', 
            hour: 'numeric', 
            minute: '2-digit',
            timeZone: 'Asia/Karachi'
          }));
        }
      } catch (e) {
        console.error("Error checking availability for alternative:", e);
      }
    }
    if (alternatives.length >= 3) break;
  }
  return alternatives;
}

// --- API Routes ---

app.get("/api/auth/url", (req, res) => {
  const url = oauth2Client.generateAuthUrl({
    access_type: "offline",
    scope: SCOPES,
    prompt: "consent",
  });
  res.json({ url });
});

app.get("/auth/callback", async (req, res) => {
  const { code } = req.query;
  try {
    const { tokens } = await oauth2Client.getToken(code as string);
    res.cookie("google_tokens", JSON.stringify(tokens), {
      httpOnly: true,
      secure: true,
      sameSite: "none",
    });
    res.send(`
      <html>
        <body>
          <script>
            if (window.opener) {
              window.opener.postMessage({ type: 'OAUTH_AUTH_SUCCESS' }, '*');
              window.close();
            } else {
              window.location.href = '/';
            }
          </script>
          <p>Authentication successful. You can close this window.</p>
        </body>
      </html>
    `);
  } catch (error) {
    console.error("Auth error:", error);
    res.status(500).send("Authentication failed");
  }
});

app.get("/api/auth/status", (req, res) => {
  res.json({ connected: !!req.cookies.google_tokens });
});

app.get("/api/appointments/check", async (req, res) => {
  const tokens = req.cookies.google_tokens;
  if (!tokens) return res.status(401).json({ error: "Not connected" });

  const { startTime } = req.query;
  if (!startTime) return res.status(400).json({ error: "startTime is required" });

  let formattedStartTime = startTime as string;
  if (!formattedStartTime.includes("+") && !formattedStartTime.includes("Z") && !/-\d{2}:\d{2}$/.test(formattedStartTime)) {
    formattedStartTime = `${formattedStartTime}+05:00`;
  }

  const start = new Date(formattedStartTime);

  try {
    const calendar = await getCalendar(JSON.parse(tokens));
    const isAvailable = await checkAvailability(calendar, start);
    
    if (isAvailable) {
      res.json({ available: true });
    } else {
      const alternatives = await findAlternativeSlots(calendar, start);
      res.json({ available: false, alternatives });
    }
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

app.post("/api/appointments/book", async (req, res) => {
  const tokens = req.cookies.google_tokens;
  if (!tokens) return res.status(401).json({ error: "Not connected to Google Calendar" });

  const { name, email, package_name, startTime } = req.body;
  
  // Ensure the time is parsed as Karachi time (+05:00) if no offset is provided
  let formattedStartTime = startTime;
  if (!startTime.includes("+") && !startTime.includes("Z") && !/-\d{2}:\d{2}$/.test(startTime)) {
    formattedStartTime = `${startTime}+05:00`;
  }
  
  const start = new Date(formattedStartTime);
  const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

  try {
    const calendar = await getCalendar(JSON.parse(tokens));
    
    const isAvailable = await checkAvailability(calendar, start);
    if (!isAvailable) {
      const alternatives = await findAlternativeSlots(calendar, start);
      return res.status(409).json({ 
        error: "Time slot unavailable", 
        alternatives 
      });
    }

    const event = {
      summary: `Booking: ${name} - ${package_name}`,
      description: `Package: ${package_name}\nClient: ${email}`,
      start: { dateTime: start.toISOString(), timeZone: "Asia/Karachi" },
      end: { dateTime: end.toISOString(), timeZone: "Asia/Karachi" },
      attendees: [{ email, displayName: name }],
    };

    const response = await calendar.events.insert({
      calendarId: "primary",
      requestBody: event,
    });

    res.json({ 
      status: "success", 
      eventId: response.data.id,
      link: response.data.htmlLink 
    });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

app.get("/api/appointments/find", async (req, res) => {
  const tokens = req.cookies.google_tokens;
  if (!tokens) return res.status(401).json({ error: "Not connected" });

  const { name } = req.query;
  try {
    const calendar = await getCalendar(JSON.parse(tokens));
    const response = await calendar.events.list({
      calendarId: "primary",
      q: name as string,
      timeMin: new Date().toISOString(),
      singleEvents: true,
      orderBy: "startTime",
    });

    const events = response.data.items || [];
    const filtered = events.filter(e => 
      e.summary?.toLowerCase().includes((name as string).toLowerCase())
    ).map(e => ({
      id: e.id,
      summary: e.summary,
      start: e.start?.dateTime || e.start?.date,
    }));

    res.json(filtered);
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

app.delete("/api/appointments/:id", async (req, res) => {
  const tokens = req.cookies.google_tokens;
  if (!tokens) return res.status(401).json({ error: "Not connected" });

  try {
    const calendar = await getCalendar(JSON.parse(tokens));
    await calendar.events.delete({
      calendarId: "primary",
      eventId: req.params.id,
    });
    res.json({ status: "success" });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

app.patch("/api/appointments/:id", async (req, res) => {
  const tokens = req.cookies.google_tokens;
  if (!tokens) return res.status(401).json({ error: "Not connected" });

  const { startTime } = req.body;
  
  // Ensure the time is parsed as Karachi time (+05:00) if no offset is provided
  let formattedStartTime = startTime;
  if (!startTime.includes("+") && !startTime.includes("Z") && !/-\d{2}:\d{2}$/.test(startTime)) {
    formattedStartTime = `${startTime}+05:00`;
  }

  const start = new Date(formattedStartTime);
  const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

  try {
    const calendar = await getCalendar(JSON.parse(tokens));
    
    const isAvailable = await checkAvailability(calendar, start);
    if (!isAvailable) {
      const alternatives = await findAlternativeSlots(calendar, start);
      return res.status(409).json({ error: "Time slot unavailable", alternatives });
    }

    await calendar.events.patch({
      calendarId: "primary",
      eventId: req.params.id,
      requestBody: {
        start: { dateTime: start.toISOString(), timeZone: "Asia/Karachi" },
        end: { dateTime: end.toISOString(), timeZone: "Asia/Karachi" },
      },
    });
    res.json({ status: "success" });
  } catch (error: any) {
    res.status(500).json({ error: error.message });
  }
});

// --- Vite Integration ---

async function startServer() {
  const isProduction = process.env.NODE_ENV === "production";

  if (!isProduction) {
    const vite = await createViteServer({
      server: { middlewareMode: true },
      appType: "spa",
    });
    app.use(vite.middlewares);
  } else {
    const distPath = path.resolve(__dirname, "dist");
    app.use(express.static(distPath));
    app.get("*", (req, res) => {
      res.sendFile(path.resolve(distPath, "index.html"));
    });
  }

  app.listen(PORT, "0.0.0.0", () => {
    console.log(`Server running on http://localhost:${PORT}`);
  });
}

startServer();
