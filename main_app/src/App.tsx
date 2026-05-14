import React, { useState, useEffect, useRef, useCallback } from "react";
import { motion, AnimatePresence } from "motion/react";
import { Send, Home } from "lucide-react";
import { createChat, isRateLimitError, isServerError, isTimeoutError } from "./services/chatService";
import type { PropertyResult } from "./services/chatService";

interface Message {
  role: "user" | "model";
  content: string;
  properties?: PropertyResult[];
  searchUrl?: string;
}

const MESSAGES_KEY = "hhp_chat_msgs";
const HISTORY_KEY = "hhp_chat_hist";
const ACTIVITY_KEY = "hhp_chat_ts";
const INACTIVITY_MS = 5 * 60 * 1000;

type AiChatConfig = { proxyUrl?: string; archiveUrl?: string };
const aiConfig = (window as Window & { aiChatConfig?: AiChatConfig }).aiChatConfig ?? ({} as AiChatConfig);
const archiveUrl = aiConfig.archiveUrl ?? "/properties/";

const INITIAL_MESSAGE: Message = {
  role: "model",
  content:
    "Hello! I'm your Panama real estate assistant from House Hunters Panama. I can help you find properties, compare neighborhoods, and answer questions about buying or renting in Panama.\n\nWhat are you looking for?",
};

const QUICK_SEARCHES = [
  { label: "Beach Properties", query: "Show me ocean view or beach properties" },
  { label: "Under $300k", query: "Properties for sale under $300,000" },
  { label: "Boquete", query: "Tell me about properties in Boquete" },
  { label: "Panama City", query: "Show me apartments in Panama City" },
];

function loadStoredChat(): { messages: Message[]; history: unknown[] } | null {
  try {
    const ts = parseInt(sessionStorage.getItem(ACTIVITY_KEY) ?? "0", 10);
    if (!ts || Date.now() - ts > INACTIVITY_MS) return null;
    const msgs = JSON.parse(sessionStorage.getItem(MESSAGES_KEY) ?? "null");
    const hist = JSON.parse(sessionStorage.getItem(HISTORY_KEY) ?? "null");
    if (Array.isArray(msgs) && Array.isArray(hist)) {
      // Refresh timestamp on page load so 5-min clock starts from now, not last message
      sessionStorage.setItem(ACTIVITY_KEY, Date.now().toString());
      return { messages: msgs, history: hist };
    }
  } catch {}
  return null;
}

function saveStoredChat(messages: Message[], history: unknown[]) {
  try {
    // Trim history to last 30 entries to avoid quota errors silently eating the timestamp save
    const trimmedHistory = Array.isArray(history) ? history.slice(-30) : [];
    sessionStorage.setItem(MESSAGES_KEY, JSON.stringify(messages));
    sessionStorage.setItem(HISTORY_KEY, JSON.stringify(trimmedHistory));
    sessionStorage.setItem(ACTIVITY_KEY, Date.now().toString());
  } catch {}
}

function clearStoredChat() {
  [MESSAGES_KEY, HISTORY_KEY, ACTIVITY_KEY].forEach((k) => sessionStorage.removeItem(k));
}

function stripEmoji(text: string): string {
  return text
    .replace(/[\u{1F000}-\u{1FFFF}\u{2600}-\u{27BF}\u{2300}-\u{23FF}]/gu, "")
    .replace(/ {2,}/g, " ")
    .trim();
}

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
          className="text-[#1B6CA8] underline hover:text-[#155a90]"
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
      className="block bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md hover:border-[#1B6CA8]/40 transition-all"
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
        <div className="w-full h-16 bg-blue-50" />
      )}
      <div className="p-3">
        <p className="font-semibold text-xs text-gray-800 line-clamp-2 leading-tight">
          {prop.title}
        </p>
        <p className="text-[#1B6CA8] font-bold text-sm mt-1">{displayPrice}</p>
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

export default function App() {
  const [initialData] = useState(() => loadStoredChat());
  const [chat, setChat] = useState(() => createChat(initialData?.history ?? []));
  const [messages, setMessages] = useState<Message[]>(
    () => initialData?.messages ?? [INITIAL_MESSAGE]
  );
  const [input, setInput] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const inactivityRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages, isLoading]);

  const resetInactivity = useCallback(() => {
    if (inactivityRef.current) clearTimeout(inactivityRef.current);
    inactivityRef.current = setTimeout(() => {
      clearStoredChat();
      setMessages([INITIAL_MESSAGE]);
      setChat(createChat());
    }, INACTIVITY_MS);
  }, []);

  const clearChat = useCallback(() => {
    if (inactivityRef.current) clearTimeout(inactivityRef.current);
    inactivityRef.current = null;
    clearStoredChat();
    setMessages([INITIAL_MESSAGE]);
    setChat(createChat());
  }, []);

  useEffect(() => {
    if (initialData) resetInactivity();
    return () => {
      if (inactivityRef.current) clearTimeout(inactivityRef.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const sendMessage = async (text?: string) => {
    const userMsg = (text || input).trim();
    if (!userMsg || isLoading) return;

    setInput("");
    if (textareaRef.current) {
      textareaRef.current.style.height = "auto";
    }
    setIsLoading(true);
    resetInactivity();

    const withUser: Message[] = [...messages, { role: "user", content: userMsg }];
    setMessages(withUser);

    try {
      const { text: responseText, properties, searchUrl } = await chat.sendUserMessage(userMsg);
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

  const showQuickSearches = messages.length === 1 && !isLoading;

  return (
    <div className="h-full flex flex-col bg-gray-50 text-gray-800 font-sans">
      {/* Header */}
      <div className="shrink-0 bg-[#1B6CA8] text-white px-4 py-3 flex items-center gap-3">
        <div className="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
          <Home size={16} />
        </div>
        <div className="flex-1">
          <p className="font-semibold text-sm leading-tight">House Hunters Panama</p>
          <p className="text-white/70 text-xs">AI Property Assistant</p>
        </div>
        {messages.length > 1 && (
          <button
            onClick={clearChat}
            className="text-white/60 hover:text-white text-xs transition-colors px-2 py-1 rounded"
          >
            Clear
          </button>
        )}
      </div>

      {/* Messages */}
      <div ref={scrollRef} className="flex-1 overflow-y-auto p-4 space-y-4">
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
                      ? "bg-[#1B6CA8] text-white rounded-tr-none"
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
                        className="mt-2 block text-center text-xs font-medium text-[#1B6CA8] hover:underline py-1"
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

      {/* Quick search buttons */}
      {showQuickSearches && (
        <div className="shrink-0 px-4 pb-2 grid grid-cols-2 gap-2">
          {QUICK_SEARCHES.map((q) => (
            <button
              key={q.label}
              onClick={() => sendMessage(q.query)}
              className="text-left text-xs bg-white border border-gray-200 rounded-xl px-3 py-2.5 hover:bg-blue-50 hover:border-[#1B6CA8]/50 transition-colors shadow-sm"
            >
              {q.label}
            </button>
          ))}
        </div>
      )}

      {/* Input */}
      <div className="shrink-0 px-4 py-3 border-t border-gray-200 bg-white">
        <div className="flex items-end gap-2">
          <textarea
            ref={textareaRef}
            value={input}
            onChange={(e) => {
              setInput(e.target.value);
              e.target.style.height = "auto";
              e.target.style.height = Math.min(e.target.scrollHeight, 120) + "px";
            }}
            onKeyDown={(e) => {
              if (e.key === "Enter" && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
              }
            }}
            placeholder="Search properties or ask a question…"
            rows={1}
            className="flex-1 bg-gray-100 rounded-2xl py-3 px-4 text-sm text-gray-800 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1B6CA8]/30 focus:bg-white transition resize-none overflow-hidden"
          />
          <button
            onClick={() => sendMessage()}
            disabled={isLoading || !input.trim()}
            className="shrink-0 flex items-center justify-center h-9 w-9 rounded-full bg-[#1B6CA8] text-white hover:bg-[#155a90] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed mb-0.5"
          >
            <Send size={16} />
          </button>
        </div>
      </div>
    </div>
  );
}
