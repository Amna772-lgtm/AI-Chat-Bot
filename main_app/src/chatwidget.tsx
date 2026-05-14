import { useState } from "react";
import { X, Home } from "lucide-react";
import App from "./App";

export default function ChatWidget() {
  const [open, setOpen] = useState(false);

  return (
    <>
      {/* Floating button */}
      <button
        onClick={() => setOpen(true)}
        title="Find Panama Properties"
        className="fixed bottom-6 right-6 z-[9999] bg-[#1B6CA8] text-white p-4 rounded-full shadow-xl hover:bg-[#155a90] transition-colors"
      >
        <Home size={24} />
      </button>

      {/* Chat panel */}
      {open && (
        <div className="fixed inset-0 z-[9998] bg-black/40 flex items-end md:items-center md:justify-end md:pr-6">
          <div className="relative w-full md:w-[400px] h-[90vh] md:h-[640px] bg-gray-50 rounded-t-2xl md:rounded-2xl shadow-2xl overflow-hidden">
            <button
              onClick={() => setOpen(false)}
              className="absolute top-3 right-3 z-10 text-white/80 hover:text-white transition-colors"
            >
              <X size={20} />
            </button>
            <App />
          </div>
        </div>
      )}
    </>
  );
}
