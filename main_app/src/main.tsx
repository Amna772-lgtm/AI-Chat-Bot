import {StrictMode} from 'react';
import {createRoot} from 'react-dom/client';
import ChatWidget from './chatwidget';
import './index.css';

let container = document.getElementById('ai-chat-widget');
if (!container) {
  container = document.createElement('div');
  container.id = 'ai-chat-widget';
  document.body.appendChild(container);
}

createRoot(container).render(
  <StrictMode>
    <ChatWidget />
  </StrictMode>
);
