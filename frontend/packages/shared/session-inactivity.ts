export const IDLE_TIMEOUT_MS = 30 * 60 * 1000;
interface Stamp {
  at: number;
  ended: boolean;
}
interface Options {
  namespace: string;
  server: () => string;
  readToken: () => Promise<string | null>;
  expire: (token: string) => Promise<void>;
  heartbeat: (token: string, idleSeconds: number) => Promise<void>;
}

// Only the public numeric token ID and timestamps are persisted here, never a secret.
export function createSessionActivity(namespace: string, server: () => string) {
  const memory = new Map<string, Stamp>();
  const key = (token: string) => {
    const publicId = token.match(/^(\d+)\|/)?.[1] || "test-session";
    return `${namespace}:idle:${encodeURIComponent(server())}:${publicId}`;
  };
  function save(token: string, stamp: Stamp) {
    const k = key(token);
    memory.set(k, stamp);
    try {
      localStorage.setItem(k, JSON.stringify(stamp));
    } catch {
      /* Fail closed after reload if persistence is unavailable. */
    }
  }
  function read(token: string): Stamp {
    let stamp: Stamp | undefined;
    try {
      stamp = JSON.parse(localStorage.getItem(key(token)) || "null") as
        Stamp | undefined;
    } catch {
      /* use in-memory state */
    }
    stamp ||= memory.get(key(token));
    if (!stamp) {
      stamp = { at: Date.now(), ended: false };
      save(token, stamp);
    }
    if (
      !Number.isFinite(stamp.at) ||
      stamp.at > Date.now() + 60000 ||
      typeof stamp.ended !== "boolean"
    )
      return { at: 0, ended: true };
    return stamp;
  }
  return {
    begin(token: string) {
      save(token, { at: Date.now(), ended: false });
    },
    end(token: string) {
      save(token, { at: 0, ended: true });
    },
    expired(token: string) {
      const stamp = read(token);
      return stamp.ended || Date.now() - stamp.at >= IDLE_TIMEOUT_MS;
    },
    lastActivity(token: string) {
      return read(token).at;
    },
    touch(token: string) {
      const stamp = read(token);
      if (!stamp.ended && Date.now() - stamp.at < IDLE_TIMEOUT_MS)
        save(token, { at: Date.now(), ended: false });
    },
  };
}

export function startSessionActivity(options: Options) {
  const activity = createSessionActivity(options.namespace, options.server);
  let running = false,
    stopped = false,
    lastInteractionCheck = -Infinity;
  let lastSentToken = "",
    lastSentActivity = -1,
    sentAt = -Infinity;
  async function check(interaction = false) {
    if (running || stopped) return;
    running = true;
    try {
      const token = await options.readToken();
      if (!token || stopped) return;
      if (activity.expired(token)) {
        activity.end(token);
        await options.expire(token);
        return;
      }
      if (interaction && document.visibilityState === "visible")
        activity.touch(token);
      const last = activity.lastActivity(token);
      // Polling, navigation, visibility and push notifications do not renew activity.
      if (
        document.visibilityState !== "visible" ||
        (last === lastSentActivity && token === lastSentToken) ||
        Date.now() - sentAt < 60000
      )
        return;
      sentAt = Date.now();
      try {
        await options.heartbeat(
          token,
          Math.max(0, Math.floor((Date.now() - last) / 1000)),
        );
        lastSentToken = token;
        lastSentActivity = last;
      } catch {
        /* Existing HTTP interceptors handle 401; retry a network failure later. */
      }
    } finally {
      running = false;
    }
  }
  function interaction(event: Event) {
    if (!event.isTrusted || Date.now() - lastInteractionCheck < 1000) return;
    lastInteractionCheck = Date.now();
    void check(true).catch(() => undefined);
  }
  function resume() {
    void check().catch(() => undefined);
  }
  const events = [
    "pointerdown",
    "pointermove",
    "keydown",
    "touchstart",
    "touchmove",
    "wheel",
  ];
  events.forEach((name) =>
    window.addEventListener(name, interaction, { passive: true }),
  );
  document.addEventListener("visibilitychange", resume);
  window.addEventListener("pageshow", resume);
  const timer = window.setInterval(resume, 1000);
  resume();
  return () => {
    stopped = true;
    window.clearInterval(timer);
    events.forEach((name) => window.removeEventListener(name, interaction));
    document.removeEventListener("visibilitychange", resume);
    window.removeEventListener("pageshow", resume);
  };
}
