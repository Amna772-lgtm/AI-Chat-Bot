import { useState } from "react";
import { MessageCircle, X } from "lucide-react";
import App from "./App";

export default function ChatWidget() {
  const [open, setOpen] = useState(false);

  return (
    <>
      {/* Floating button */}
      <button
        onClick={() => setOpen((o) => !o)}
        title={open ? "Close" : "Find Panama Properties"}
        className="fixed bottom-6 right-6 z-[9999] bg-[var(--ai-primary)] p-4 rounded-full shadow-xl hover:bg-[var(--ai-primary-hover)] transition-colors cursor-pointer"
      >
        {open ? <X size={22} color="#2d1f14" /> : <MessageCircle size={22} color="#2d1f14" />}
      </button>

      {/* Chat panel */}
      {open && (
        <div className="fixed inset-0 z-[9998] bg-black/40 md:bg-transparent flex items-end md:items-end md:justify-end md:pb-[88px] md:pr-6">
          <div className="relative w-full md:w-[380px] h-[80vh] md:h-[520px] bg-gray-50 rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden">
            <App onClose={() => setOpen(false)} />
          </div>
        </div>
      )}
    </>
  );
}
