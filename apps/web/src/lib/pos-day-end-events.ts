export type PosDayEndRefreshReason = "sale" | "expense" | "collection" | "session";

export type PosDayEndRefreshPayload = {
  reason: PosDayEndRefreshReason;
  sessionId?: number | null;
  at: number;
};

const POS_DAY_END_REFRESH_EVENT = "powersa:pos-day-end-refresh";
const POS_DAY_END_REFRESH_STORAGE_KEY = "powersa:pos-day-end-refresh";
const POS_DAY_END_REFRESH_CHANNEL = "powersa-pos-day-end";

function isPayload(value: unknown): value is PosDayEndRefreshPayload {
  if (!value || typeof value !== "object") {
    return false;
  }

  const payload = value as Partial<PosDayEndRefreshPayload>;
  return typeof payload.reason === "string" && typeof payload.at === "number";
}

function readPayload(value: string | null): PosDayEndRefreshPayload | null {
  if (!value) {
    return null;
  }

  try {
    const parsed = JSON.parse(value);
    return isPayload(parsed) ? parsed : null;
  } catch {
    return null;
  }
}

export function notifyPosDayEndRefresh(reason: PosDayEndRefreshReason, sessionId?: number | null): void {
  if (typeof window === "undefined") {
    return;
  }

  const payload: PosDayEndRefreshPayload = {
    reason,
    sessionId,
    at: Date.now(),
  };

  window.dispatchEvent(new CustomEvent(POS_DAY_END_REFRESH_EVENT, { detail: payload }));

  try {
    window.localStorage.setItem(POS_DAY_END_REFRESH_STORAGE_KEY, JSON.stringify(payload));
  } catch {
    // Cross-tab refresh is best-effort; same-tab listeners still receive the custom event.
  }

  if ("BroadcastChannel" in window) {
    try {
      const channel = new BroadcastChannel(POS_DAY_END_REFRESH_CHANNEL);
      channel.postMessage(payload);
      channel.close();
    } catch {
      // Some embedded browsers disable BroadcastChannel.
    }
  }
}

export function subscribePosDayEndRefresh(listener: (payload: PosDayEndRefreshPayload) => void): () => void {
  if (typeof window === "undefined") {
    return () => {};
  }

  const handleCustomEvent = (event: Event) => {
    const payload = (event as CustomEvent<PosDayEndRefreshPayload>).detail;
    if (isPayload(payload)) {
      listener(payload);
    }
  };

  const handleStorageEvent = (event: StorageEvent) => {
    if (event.key !== POS_DAY_END_REFRESH_STORAGE_KEY) {
      return;
    }

    const payload = readPayload(event.newValue);
    if (payload) {
      listener(payload);
    }
  };

  const channel = "BroadcastChannel" in window ? new BroadcastChannel(POS_DAY_END_REFRESH_CHANNEL) : null;
  if (channel) {
    channel.onmessage = (event) => {
      if (isPayload(event.data)) {
        listener(event.data);
      }
    };
  }

  window.addEventListener(POS_DAY_END_REFRESH_EVENT, handleCustomEvent);
  window.addEventListener("storage", handleStorageEvent);

  return () => {
    window.removeEventListener(POS_DAY_END_REFRESH_EVENT, handleCustomEvent);
    window.removeEventListener("storage", handleStorageEvent);
    channel?.close();
  };
}
