import { useState, useEffect, useRef } from "react";
import { MessageCircleMore, X } from "lucide-react";
import App from "./App";

type WidgetConfig = {
  teaserEnabled?: boolean;
  teaserMessage?: string;
  teaserDelay?: number;
  widgetName?: string;
};
const cfg = (window as Window & { aiChatConfig?: WidgetConfig }).aiChatConfig ?? ({} as WidgetConfig);
const teaserEnabled  = cfg.teaserEnabled  ?? false;
const teaserMessage  = cfg.teaserMessage  ?? "👋 Looking for a property in Panama? Ask me anything!";
const teaserDelaySec = cfg.teaserDelay    ?? 3;

const splitIdx    = teaserMessage.indexOf('?');
const teaserTitle = splitIdx !== -1 ? teaserMessage.slice(0, splitIdx + 1) : teaserMessage;
const teaserBody  = splitIdx !== -1 ? teaserMessage.slice(splitIdx + 1).trim() : '';

const TEASER_SEEN_KEY = "hhp_teaser_seen";

function teaserWasSeen(): boolean {
  try { return sessionStorage.getItem(TEASER_SEEN_KEY) === "1"; } catch { return false; }
}
function markTeaserSeen(): void {
  try { sessionStorage.setItem(TEASER_SEEN_KEY, "1"); } catch {}
}

export default function ChatWidget() {
  const [open, setOpen]             = useState(false);
  const [showTeaser, setShowTeaser] = useState(false);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!teaserEnabled) return;
    if (teaserWasSeen()) return;

    timerRef.current = setTimeout(() => {
      setShowTeaser(true);
    }, teaserDelaySec * 1000);

    return () => { if (timerRef.current) clearTimeout(timerRef.current); };
  }, []);

  useEffect(() => {
    if (open) setShowTeaser(false);
  }, [open]);

  function handleTeaserDismiss(e: React.MouseEvent) {
    e.stopPropagation();
    setShowTeaser(false);
    markTeaserSeen();
  }

  function handleTeaserClick() {
    setShowTeaser(false);
    markTeaserSeen();
    setOpen(true);
  }

  return (
    <>
      {/* Teaser bubble */}
      {showTeaser && !open && (
        <div className="ai-teaser-wrapper fixed bottom-[88px] right-6 z-[9999]">

          {/* Card */}
          <div
            onClick={handleTeaserClick}
            className="ai-teaser-bubble animate-teaser-in relative w-[290px] cursor-pointer bg-white rounded-2xl px-5 pt-5 pb-5"
            style={{ boxShadow: '0 8px 40px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.06)' }}
          >
            {/* Close */}
            <button
              onClick={handleTeaserDismiss}
              className="absolute top-3.5 right-4 text-gray-400 hover:text-[#2d1f14] transition-colors cursor-pointer"
              aria-label="Dismiss"
            >
              <X size={14} />
            </button>

            {/* Avatar + title */}
            <div className="flex items-start gap-3 mb-3">
              <div className="w-9 h-9 rounded-full bg-[var(--ai-primary)] flex items-center justify-center shrink-0">
                <span className="text-white text-xs font-bold tracking-tight">H</span>
              </div>
              <p className="text-[#2d1f14] font-bold text-[15px] leading-snug pr-6">{teaserTitle}</p>
            </div>

            {/* Subtext */}
            {teaserBody && (
              <p className="text-gray-500 text-sm leading-relaxed pl-12 mb-4">{teaserBody}</p>
            )}

            {/* CTA pill */}
            <div className="pl-12">
              <span className="inline-block px-4 py-1.5 rounded-full border border-[var(--ai-primary)] text-[var(--ai-primary)] text-[11px] font-semibold tracking-widest uppercase">
                Ask me anything →
              </span>
            </div>
          </div>
        </div>
      )}

      {/* Floating toggle button */}
      <button
        onClick={() => setOpen((o) => !o)}
        title={open ? "Close" : "Find Panama Properties"}
        className={`fixed bottom-6 right-6 z-[9999] bg-[var(--ai-primary)] p-4 rounded-full shadow-xl hover:bg-[var(--ai-primary-hover)] transition-colors cursor-pointer ${open ? "hidden md:block" : ""}`}
      >
        {open ? <X size={22} color="#ffffff" /> : <MessageCircleMore size={22} color="#ffffff" />}
      </button>

      {/* Chat panel */}
      {open && (
        <div className="fixed inset-0 z-[9998] bg-black/40 md:bg-transparent flex items-end justify-center md:justify-end md:pb-[88px] md:pr-6">
          <div className="relative w-[90%] mx-auto md:mx-0 md:w-[380px] h-[80vh] md:h-[520px] bg-gray-50 rounded-2xl shadow-2xl overflow-hidden">
            <App onClose={() => setOpen(false)} />
          </div>
        </div>
      )}
    </>
  );
}
