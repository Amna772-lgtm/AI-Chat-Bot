import React, { useState, useEffect, useRef, useCallback } from "react"; // useCallback kept for clearChat
import { flushSync } from "react-dom";
import { motion, AnimatePresence } from "motion/react";
import { Send, Home, X, Mic, MicOff } from "lucide-react";
import { createChat, isRateLimitError, isServerError, isTimeoutError } from "./services/chatService";
import type { PropertyResult } from "./services/chatService";

interface Message {
  role: "user" | "model";
  content: string;
  properties?: PropertyResult[];
  searchUrl?: string;
}

const MESSAGES_KEY = "hhp_chat_msgs";
const HISTORY_KEY  = "hhp_chat_hist";
const SESSION_FLAG = "hhp_chat_init"; // sessionStorage: marks this tab as initialized
const BC_CHANNEL   = "hhp_chat_bc";  // BroadcastChannel: detects live tabs
const LANG_KEY     = "hhp_chat_lang";

type AiChatConfig = { proxyUrl?: string; archiveUrl?: string; widgetName?: string; welcomeMessage?: string; widgetSubtitle?: string };
const aiConfig = (window as Window & { aiChatConfig?: AiChatConfig }).aiChatConfig ?? ({} as AiChatConfig);
const archiveUrl = aiConfig.archiveUrl ?? "/properties/";

const INITIAL_MESSAGE: Message = {
  role: "model",
  content: aiConfig.welcomeMessage ?? "Hi! I'm your AI real estate assistant for Panama. How can I help you today?",
};

function loadFromStorage(): { messages: Message[]; history: unknown[] } | null {
  try {
    const msgs = JSON.parse(localStorage.getItem(MESSAGES_KEY) ?? "null");
    const hist = JSON.parse(localStorage.getItem(HISTORY_KEY)  ?? "null");
    if (Array.isArray(msgs) && Array.isArray(hist)) return { messages: msgs, history: hist };
  } catch {}
  return null;
}

function saveStoredChat(messages: Message[], history: unknown[]) {
  try {
    const trimmedHistory = Array.isArray(history) ? history.slice(-30) : [];
    localStorage.setItem(MESSAGES_KEY, JSON.stringify(messages));
    localStorage.setItem(HISTORY_KEY,  JSON.stringify(trimmedHistory));
  } catch {}
}

function clearStoredChat() {
  [MESSAGES_KEY, HISTORY_KEY].forEach((k) => localStorage.removeItem(k));
}

function stripEmoji(text: string): string {
  return text
    .replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}]/gu, "")
    .replace(/ {2,}/g, " ")
    .trim();
}

// Panama place-name grammar hints for the Web Speech API (JSGF format).
// Biases Chrome/Edge's speech engine toward local place names before transcription.
// Other browsers silently ignore it. Update place names here as needed.
const PANAMA_SPEECH_GRAMMAR = `#JSGF V1.0;
grammar panama_locations;
public <place> =
  Boquete | Alto Boquete | Bajo Boquete | Jaramillo | Valle Escondido | Lucero |
  Los Molinos | Boquete Canyon Village |
  David | Volcan | Volcan Baru | Cerro Punta | Dolega | Gualaca |
  Potrerillos | Potrerillos Arriba | Potrerillos Abajo | Palmira | Caldera |
  Chiriqui | Bugaba | La Concepcion | Concepcion | Paso Ancho | Renacimiento |
  Puerto Armuelles | Boca Chica | Las Lajas | Playa Las Lajas | San Felix |
  Tole | Remedios | Alanje | Golfo de Chiriqui | Isla Boca Brava | Isla Parida |
  Pedasi | Playa Venao | Cambutal | Las Tablas | Chitre | Herrera |
  Los Santos | Mariato | Coiba | Coiba Island | Aguadulce | Penonome |
  El Valle de Anton | Anton | Cocle | Santa Fe | Sona | Santiago | Veraguas |
  Coronado | Playa Coronado | Gorgona | Nueva Gorgona | Punta Barco |
  San Carlos | El Palmar | Rio Hato | Buenaventura | Farallon |
  Playa Blanca | Bijao | Santa Clara | Playa Santa Clara | Playa Farallon |
  Playa El Palmar | Playa Gorgona | Punta Chame | Playa Punta Chame |
  Playa Bonita | Playa Escondida | Chame | Capira | Veracruz |
  Buenaventura Golf and Beach Resort |
  Panama City | Casco Viejo | Casco Antiguo | San Felipe | Bella Vista |
  El Cangrejo | Obarrio | Marbella | Punta Paitilla | Paitilla |
  Punta Pacifica | Costa del Este | Costa Sur | Coco del Mar |
  San Francisco | Rio Abajo | Parque Lefevre | Juan Diaz | Costa Verde |
  Albrook | Clayton | Ancon | Balboa Avenue | Avenida Balboa | Amador |
  Causeway | Calzada de Amador | Ancon Hill | Cerro Ancon |
  Panama Canal | Canal de Panama | Miraflores | Gamboa | Soberania | Summit |
  Tocumen | Pacora | Chepo | Panama Oeste | Arraijan | La Chorrera |
  Taboga | Isla Taboga | Pearl Islands | Contadora | Saboga |
  Ocean Reef Islands | Ocean Reef | The Ocean Club | Costa del Mar |
  JW Marriott Panama | Trump Tower Panama | Soho City Center |
  Condado del Rey |
  Bocas del Toro | Isla Colon | Bastimentos | Carenero | Red Frog Beach |
  Bluff Beach | Starfish Beach | Boca del Drago | Changuinola | Almirante |
  Isla Solarte | Isla Cristobal | Portobelo | Colon | Isla Grande |
  Nombre de Dios | Guna Yala | San Blas | Kuna Yala | Isla Perro |
  Cayos Holandeses | Playa Bluff | Cocle del Norte;
`;

// Renders plain text with **bold** and [link](url) markdown parsed into elements
function renderText(text: string): React.ReactNode {
  const parts = text.split(/(\*\*[^*\n]+\*\*|\[[^\]\n]+\]\([^)\n]+\))/g);
  return parts.map((part, i) => {
    const link = part.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
    if (link) {
      return (
        <a
          key={i}
          href={link[2]}
          target="_blank"
          rel="noopener noreferrer"
          className="text-[var(--ai-secondary)] underline hover:text-[var(--ai-primary)]"
        >
          {link[1]}
        </a>
      );
    }
    const bold = part.match(/^\*\*([^*]+)\*\*$/);
    if (bold) return <strong key={i}>{bold[1]}</strong>;
    return <React.Fragment key={i}>{part}</React.Fragment>;
  });
}

function PropertyCard({ prop }: { prop: PropertyResult }) {
  const displayPrice =
    prop.pricing.price > 0
      ? prop.pricing.formatted
      : prop.features.rent_amount > 0
      ? `$${prop.features.rent_amount.toLocaleString()}/mo`
      : "Price on request";

  const img = prop.media.thumbnail || prop.media.featured_image;
  const specs = [
    prop.details.bedrooms > 0 && `${prop.details.bedrooms} bd`,
    prop.details.bathrooms > 0 && `${prop.details.bathrooms} ba`,
    prop.details.area.size > 0 && `${prop.details.area.size}m²`,
  ].filter(Boolean);

  return (
    <a
      href={prop.permalink}
      target="_blank"
      rel="noopener noreferrer"
      className="block bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md hover:border-[var(--ai-primary)] transition-all"
    >
      {img ? (
        <img
          src={img}
          alt={prop.title}
          className="w-full h-28 object-cover"
          onError={(e) => {
            (e.target as HTMLImageElement).style.display = "none";
          }}
        />
      ) : (
        <div className="w-full h-16 bg-[var(--ai-primary)]/20" />
      )}
      <div className="p-3">
        <p className="font-semibold text-xs text-gray-800 line-clamp-2 leading-tight">
          {prop.title}
        </p>
        <p className="text-[var(--ai-secondary)] font-bold text-sm mt-1">{displayPrice}</p>
        <p className="text-gray-400 text-xs mt-0.5 truncate">
          {prop.location.neighborhood || prop.location.city}
        </p>
        {specs.length > 0 && (
          <div className="flex gap-2 text-xs text-gray-500 mt-1">
            {specs.map((s, i) => (
              <span key={i}>{s}</span>
            ))}
          </div>
        )}
      </div>
    </a>
  );
}

export default function App({ onClose }: { onClose?: () => void }) {
  const [chat, setChat] = useState(() => createChat([]));
  const [messages, setMessages] = useState<Message[]>([INITIAL_MESSAGE]);
  const [input, setInput] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [isListening, setIsListening] = useState(false);
  const [sttError, setSttError] = useState("");
  const [sttLang, setSttLang] = useState<"en-US" | "es-PA">(() => {
    try { return (localStorage.getItem(LANG_KEY) as "en-US" | "es-PA") ?? "en-US"; }
    catch { return "en-US"; }
  });
  const scrollRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const recognitionRef = useRef<SpeechRecognition | null>(null);
  const transcriptRef = useRef("");
  const autoSubmitTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const suppressSubmitRef  = useRef(false);
  const finalTranscriptRef = useRef("");
  const shouldStopRef      = useRef(false); // when true, next onend finalizes instead of restarting
  const supportsSTT =
    typeof window !== "undefined" &&
    ("SpeechRecognition" in window || "webkitSpeechRecognition" in window);
  const micAvailable = supportsSTT && typeof window !== "undefined" && window.isSecureContext;

  // Determine whether to restore history using BroadcastChannel.
  // If another tab replies to our ping, we're in an active browser session → restore.
  // If no reply within 200ms, the browser was just opened → start fresh and clear stale data.
  useEffect(() => {
    const bc = new BroadcastChannel(BC_CHANNEL);

    if (sessionStorage.getItem(SESSION_FLAG)) {
      // Tab already initialized (same-tab navigation) — restore history and answer pings.
      const stored = loadFromStorage();
      if (stored) {
        setMessages(stored.messages);
        setChat(createChat(stored.history));
      }
      bc.onmessage = (e) => { if (e.data === "ping") bc.postMessage("pong"); };
      return () => bc.close();
    }

    let settled = false;

    bc.onmessage = (e) => {
      if (e.data === "pong" && !settled) {
        settled = true;
        sessionStorage.setItem(SESSION_FLAG, "1");
        const stored = loadFromStorage();
        if (stored) {
          setMessages(stored.messages);
          setChat(createChat(stored.history));
        }
        bc.onmessage = (e2) => { if (e2.data === "ping") bc.postMessage("pong"); };
      }
    };

    bc.postMessage("ping");

    const timer = setTimeout(() => {
      if (!settled) {
        settled = true;
        sessionStorage.setItem(SESSION_FLAG, "1");
        // No other tabs alive → browser was just opened. Wipe stale localStorage data.
        clearStoredChat();
        bc.onmessage = (e) => { if (e.data === "ping") bc.postMessage("pong"); };
      }
    }, 200);

    return () => { clearTimeout(timer); bc.close(); };
  }, []);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages, isLoading]);

  const clearChat = useCallback(() => {
    clearStoredChat();
    setMessages([INITIAL_MESSAGE]);
    setChat(createChat());
  }, []);

  const toggleLang = () => {
    const next = sttLang === "en-US" ? "es-PA" : "en-US";
    setSttLang(next);
    try { localStorage.setItem(LANG_KEY, next); } catch {}
  };

  const sendMessage = async (text?: string, fromVoice = false) => {
    const userMsg = (text || input).trim();
    if (!userMsg || isLoading) return;

    setInput("");
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
    }
    setIsLoading(true);

    const withUser: Message[] = [...messages, { role: "user", content: userMsg }];
    setMessages(withUser);

    try {
      const { text: responseText, properties, searchUrl } = await chat.sendUserMessage(userMsg, fromVoice);
      const modelMsg: Message = {
        role: "model",
        content:
          stripEmoji(responseText) ||
          "I couldn't find information on that. Please try a different search.",
        properties: properties.length > 0 ? properties : undefined,
        searchUrl: searchUrl,
      };
      const withModel = [...withUser, modelMsg];
      setMessages(withModel);
      saveStoredChat(withModel, chat.getHistory());
    } catch (error: unknown) {
      setMessages((prev) => [
        ...prev,
        {
          role: "model",
          content: isRateLimitError(error)
            ? "You've hit the hourly message limit. Please wait a few minutes and try again."
            : isTimeoutError(error)
            ? "The request timed out. Please try again."
            : isServerError(error)
            ? "The server encountered an error. Please try again in a moment."
            : "Something went wrong. Please try again.",
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  const toggleListening = () => {
    if (isListening) {
      shouldStopRef.current = true;
      if (autoSubmitTimerRef.current) { clearTimeout(autoSubmitTimerRef.current); autoSubmitTimerRef.current = null; }
      recognitionRef.current?.stop(); // onend will finalize and submit
      return;
    }

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const SR = (window as any).SpeechRecognition ?? (window as any).webkitSpeechRecognition;
    const recognition: SpeechRecognition = new SR();
    recognition.lang = sttLang;
    recognition.interimResults = true;
    recognition.continuous = false;
    // Attach Panama place-name grammar hints (Chrome/Edge only; others silently ignore)
    try {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const SpeechGrammarList = (window as any).SpeechGrammarList ?? (window as any).webkitSpeechGrammarList;
      if (SpeechGrammarList) {
        const gl = new SpeechGrammarList();
        gl.addFromString(PANAMA_SPEECH_GRAMMAR, 1);
        recognition.grammars = gl;
      }
    } catch { /* grammar hints are advisory — ignore any errors */ }
    recognitionRef.current = recognition;
    transcriptRef.current   = "";
    finalTranscriptRef.current = "";
    shouldStopRef.current   = false;

    recognition.onstart = () => {
      setIsListening(true);
      setSttError("");
      console.log("[STT] started");
    };

    recognition.addEventListener("speechstart", () => console.log("[STT] speech detected"));
    recognition.addEventListener("speechend",   () => console.log("[STT] speech ended"));

    recognition.onresult = (e: SpeechRecognitionEvent) => {
      // continuous=false: e.results contains only this utterance — no Chrome accumulation quirks
      const sessionText = Array.from(e.results)
        .map((r) => r[0].transcript)
        .join("")
        .trim();
      transcriptRef.current = sessionText;
      const display = finalTranscriptRef.current
        ? finalTranscriptRef.current + " " + sessionText
        : sessionText;
      console.log("[STT] result:", display);
      // flushSync forces React to paint before onend can clear the input
      flushSync(() => setInput(display));
      if (textareaRef.current) {
        textareaRef.current.style.height = "auto";
        textareaRef.current.style.height =
          Math.min(Math.max(textareaRef.current.scrollHeight, 45), 120) + "px";
      }
    };

    recognition.onend = () => {
      const sessionText = transcriptRef.current.trim();
      transcriptRef.current = "";
      if (sessionText) {
        finalTranscriptRef.current = finalTranscriptRef.current
          ? finalTranscriptRef.current + " " + sessionText
          : sessionText;
      }
      console.log("[STT] ended, accumulated:", finalTranscriptRef.current);

      if (shouldStopRef.current) {
        // Finalize: stop listening and submit if there's text
        shouldStopRef.current = false;
        setIsListening(false);
        const final = finalTranscriptRef.current;
        finalTranscriptRef.current = "";
        if (final && !suppressSubmitRef.current) sendMessage(final, true);
        suppressSubmitRef.current = false;
      } else {
        // Keep listening: restart recognition and reset the silence timer
        if (autoSubmitTimerRef.current) clearTimeout(autoSubmitTimerRef.current);
        autoSubmitTimerRef.current = setTimeout(() => {
          autoSubmitTimerRef.current = null;
          shouldStopRef.current = true;
          recognitionRef.current?.stop();
        }, 3000);
        // Defer start() by one tick so the browser finishes cleanup
        setTimeout(() => {
          try { recognition.start(); }
          catch {
            setIsListening(false);
            setSttError("Could not restart microphone. Please try again.");
            setTimeout(() => setSttError(""), 5000);
          }
        }, 0);
      }
    };

    recognition.onerror = (e: SpeechRecognitionErrorEvent) => {
      console.error("[STT] error:", e.error);
      shouldStopRef.current = true; // onend will finalize, not restart
      const messages: Record<string, string> = {
        "no-speech":      "No speech detected. Please try again.",
        "network":        "Speech service unavailable. Check your internet connection.",
        "not-allowed":    "Microphone access denied. Allow it in browser settings.",
        "audio-capture":  "No microphone found. Check your device.",
        "service-not-allowed": "Speech service blocked. Try enabling it in browser settings.",
        "aborted":        "",
      };
      const msg = messages[e.error] ?? "Speech recognition failed. Please try again.";
      if (msg) {
        setSttError(msg);
        setTimeout(() => setSttError(""), 5000);
      }
    };

    try {
      recognition.start();
    } catch (err) {
      console.error("[STT] failed to start:", err);
      setIsListening(false);
      setSttError("Could not start microphone. Please try again.");
      setTimeout(() => setSttError(""), 5000);
    }
  };

  const showQuickSearches = messages.length === 1 && !isLoading;

  return (
    <div className="h-full flex flex-col bg-gray-50 text-gray-800 font-sans">
      {/* Header */}
      <div className="shrink-0 bg-[var(--ai-primary)] px-4 py-3 flex items-center gap-3">
        <div className="w-9 h-9 bg-[#2d1f14]/10 rounded-full flex items-center justify-center shrink-0">
          <Home size={16} className="text-[#2d1f14]" />
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-sm leading-tight text-[#2d1f14]">{aiConfig.widgetName ?? 'House Hunter Panama'}</p>
          <p className="text-xs text-[#2d1f14]/60 flex items-center gap-1 mt-0.5">
            <span className="w-1.5 h-1.5 rounded-full bg-green-500 inline-block"></span>
            {aiConfig.widgetSubtitle ?? 'Online · AI Property Assistant'}
          </p>
        </div>
        <div className="flex items-center gap-1 shrink-0">
          {messages.length > 1 && (
            <button
              onClick={clearChat}
              className="text-[#2d1f14]/50 hover:text-[#2d1f14] text-xs transition-colors px-2 py-1 rounded cursor-pointer"
            >
              Clear
            </button>
          )}
          {onClose && (
            <button
              onClick={onClose}
              className="text-[#2d1f14]/60 hover:text-[#2d1f14] transition-colors p-1 rounded cursor-pointer"
              title="Close"
            >
              <X size={18} />
            </button>
          )}
        </div>
      </div>

      {/* Messages */}
      <div ref={scrollRef} className="flex-1 overflow-y-auto overscroll-contain p-4 space-y-4">
        <AnimatePresence initial={false}>
          {messages.map((msg, i) => (
            <motion.div
              key={i}
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.2 }}
              className={`flex ${msg.role === "user" ? "justify-end" : "justify-start"}`}
            >
              <div className="max-w-[90%]">
                <div
                  className={`px-4 py-3 rounded-2xl text-sm leading-relaxed whitespace-pre-wrap ${
                    msg.role === "user"
                      ? "bg-[var(--ai-primary)] text-[#2d1f14] rounded-tr-none"
                      : "bg-white text-gray-800 rounded-tl-none border border-gray-200 shadow-sm"
                  }`}
                >
                  {renderText(msg.content)}
                </div>

                {msg.properties && msg.properties.length > 0 && (
                  <div className="mt-2">
                    <div className="grid grid-cols-2 gap-2">
                      {msg.properties.slice(0, 2).map((prop) => (
                        <PropertyCard key={prop.id} prop={prop} />
                      ))}
                    </div>
                    {msg.properties.length > 2 && (
                      <a
                        href={msg.searchUrl || archiveUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="mt-2 block text-center text-xs font-medium text-[var(--ai-secondary)] hover:underline py-1"
                      >
                        Show all results →
                      </a>
                    )}
                  </div>
                )}
              </div>
            </motion.div>
          ))}
        </AnimatePresence>

        {isLoading && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="flex justify-start"
          >
            <div className="bg-white border border-gray-200 rounded-2xl rounded-tl-none px-4 py-3 shadow-sm text-sm text-gray-500">
              Thinking
              <span className="thinking-dot" style={{ animationDelay: "0s" }}>.</span>
              <span className="thinking-dot" style={{ animationDelay: "0.4s" }}>.</span>
              <span className="thinking-dot" style={{ animationDelay: "0.8s" }}>.</span>
            </div>
          </motion.div>
        )}
      </div>


      {/* Input */}
      <div className="shrink-0 px-4 py-3 border-t border-gray-200 bg-white">
        {sttError && (
          <p className="text-xs text-red-500 mb-2 px-1">{sttError}</p>
        )}
        <div className="flex items-center gap-2">
          <textarea
            ref={textareaRef}
            value={input}
            onChange={(e) => {
              if (autoSubmitTimerRef.current) {
                clearTimeout(autoSubmitTimerRef.current);
                autoSubmitTimerRef.current = null;
              }
              if (isListening) {
                suppressSubmitRef.current = true;
                shouldStopRef.current = true;
                recognitionRef.current?.stop();
              }
              setInput(e.target.value);
              e.target.style.height = "auto";
              e.target.style.height = Math.min(Math.max(e.target.scrollHeight, 45), 120) + "px";
            }}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
              }
            }}
            placeholder={isListening ? (sttLang === "es-PA" ? "Escucha…" : "Listening…") : "Ask a question…"}
            rows={1}
            style={{ minHeight: '40px', borderRadius: '10px', fontSize: '14px' }}
            className="flex-1 bg-gray-100 py-3 px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[var(--ai-primary)]/50 focus:bg-white transition resize-none overflow-hidden"
          />
          {supportsSTT && (
            <button
              onClick={toggleLang}
              disabled={isListening}
              title={sttLang === "en-US" ? "Switch to Spanish voice input" : "Cambiar a voz en inglés"}
              className="shrink-0 text-xs font-semibold px-2 h-9 rounded-lg bg-gray-100 text-gray-500 hover:bg-gray-200 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-colors"
            >
              {sttLang === "en-US" ? "EN" : "ES"}
            </button>
          )}
          {supportsSTT && (
            <button
              onClick={toggleListening}
              disabled={isLoading || !micAvailable}
              title={
                !micAvailable
                  ? "Microphone requires HTTPS"
                  : isListening
                  ? "Stop recording"
                  : "Speak your query"
              }
              className={`shrink-0 flex items-center justify-center h-9 w-9 rounded-full transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed ${
                isListening
                  ? "bg-red-500 text-white animate-pulse"
                  : "bg-gray-100 text-gray-500 hover:bg-gray-200"
              }`}
            >
              {isListening ? <MicOff size={16} /> : <Mic size={16} />}
            </button>
          )}
          <button
            onClick={() => sendMessage()}
            disabled={isLoading || !input.trim()}
            className="shrink-0 flex items-center justify-center h-9 w-9 rounded-full bg-[var(--ai-primary-hover)] text-white hover:bg-[var(--ai-secondary)] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <Send size={16} />
          </button>
        </div>
      </div>
    </div>
  );
}
