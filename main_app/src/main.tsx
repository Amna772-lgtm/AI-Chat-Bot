import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import ChatWidget from './chatwidget';
import './index.css';

const container = document.getElementById("ai-chat-widget");

if (container) {
  createRoot(container).render(
    <StrictMode>
      <ChatWidget />
    </StrictMode>
  );
}