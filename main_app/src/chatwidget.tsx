import { useState } from "react";
import { MessageCircleMore, X } from "lucide-react";
import App from "./App";

export default function ChatWidget() {
  const [open, setOpen] = useState(false);

  return (
    <>
      {/* Floating button */}
      <button
        onClick={() => setOpen((o) => !o)}
        title={open ? "Close" : "Find Panama Properties"}
        className={`fixed bottom-6 right-6 z-[9999] bg-[var(--ai-primary)] p-4 rounded-full shadow-xl hover:bg-[var(--ai-primary-hover)] transition-colors cursor-pointer ${open ? 'hidden md:block' : ''}`}
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
