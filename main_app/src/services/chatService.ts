export interface PropertyResult {
  id: string;
  wp_id: number;
  title: string;
  slug: string;
  permalink: string;
  location: {
    city: string;
    neighborhood: string;
    province: string;
    coordinates: { lat: number; lon: number };
  };
  details: {
    type: string;
    category: string;
    status: string;
    bedrooms: number;
    bathrooms: number;
    area: { size: number; unit: string };
    parking: string;
    rent_period: string;
    labels: string[];
  };
  pricing: {
    price: number;
    currency: string;
    formatted: string;
  };
  media: {
    featured_image: string | null;
    thumbnail: string | null;
    gallery: string[];
    gallery_count?: number;
  };
  features: {
    amenities: string[];
    extras: string[];
    view: string;
    lifestyle: string;
    furnished: string;
    rent_amount: number;
  };
  content: {
    description?: string;
    excerpt?: string;
  };
}

export interface ChatService {
  sendUserMessage(message: string): Promise<{ text: string; properties: PropertyResult[]; searchUrl?: string | null }>;
  getHistory(): unknown[];
}

type ProxyResponse =
  | { type: "text"; text: string; history: unknown[]; properties: PropertyResult[]; search_url?: string | null }
  | { type: "error"; message: string };

class ProxyChat implements ChatService {
  private history: unknown[];
  private readonly proxyUrl: string;

  constructor(initialHistory: unknown[] = []) {
    this.history = initialHistory;
    this.proxyUrl =
      (window as Window & { aiChatConfig?: { proxyUrl?: string } }).aiChatConfig?.proxyUrl ??
      `${window.location.origin}/wp-json/ai/v1/chat`;
  }

  getHistory(): unknown[] {
    return this.history;
  }

  async sendUserMessage(
    message: string
  ): Promise<{ text: string; properties: PropertyResult[]; searchUrl?: string | null }> {
    const response = await this.post({ text: message, history: this.history });

    if (response.type === "error") throw new Error(response.message);

    this.history = response.history;
    return { text: response.text, properties: response.properties ?? [], searchUrl: response.search_url };
  }

  private async post(body: object): Promise<ProxyResponse> {
    const res = await fetch(this.proxyUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });

    if (!res.ok) {
      const err = (await res.json().catch(() => ({}))) as { message?: string };
      throw new Error(err.message ?? `Server error (HTTP ${res.status})`);
    }

    return res.json() as Promise<ProxyResponse>;
  }
}

export function createChat(initialHistory: unknown[] = []): ChatService {
  return new ProxyChat(initialHistory);
}

export function isRateLimitError(err: unknown): boolean {
  const e = err as Record<string, unknown>;
  return (
    e?.status === 429 ||
    String(e?.message).includes("429") ||
    String(e?.message).toLowerCase().includes("rate_limit") ||
    String(e?.message).toLowerCase().includes("quota") ||
    String(e?.message).toLowerCase().includes("too many requests")
  );
}

export function isServerError(err: unknown): boolean {
  const e = err as Record<string, unknown>;
  const status = Number(e?.status);
  return status >= 500 && status < 600;
}

export function isTimeoutError(err: unknown): boolean {
  const msg = String((err as Record<string, unknown>)?.message ?? "").toLowerCase();
  return msg.includes("timeout") || msg.includes("timed out") || msg.includes("aborted");
}
