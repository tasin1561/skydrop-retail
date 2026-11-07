// POST /api/assistant — streams the AI shopping assistant (Server-Sent Events).
// With API_URL: proxies to {API_URL}/assistant/chat (bearer token, internal key and shopper IP added server-side).
// Without: the demo responder answers from the bundled catalog.
import { demoStream } from "@/lib/assistant/demo";
import { ASSISTANT_API, upstreamHeaders } from "@/lib/assistant/proxy";
import type { ChatRequest } from "@/lib/assistant/types";

export const dynamic = "force-dynamic";

const SSE_HEADERS = { "Content-Type": "text/event-stream; charset=utf-8", "Cache-Control": "no-cache, no-transform", "X-Accel-Buffering": "no" };

export async function POST(req: Request) {
  let body: ChatRequest;
  try {
    body = (await req.json()) as ChatRequest;
  } catch {
    return Response.json({ message: "Invalid request" }, { status: 400 });
  }
  const message = typeof body.message === "string" ? body.message.trim().slice(0, 2000) : "";
  if (!message) return Response.json({ message: "Type a message first.", errors: { message: ["Required"] } }, { status: 422 });
  const clean: ChatRequest = {
    conversationId: typeof body.conversationId === "string" ? body.conversationId : null,
    message,
    store: typeof body.store === "string" ? body.store : undefined,
    locale: body.locale === "bn" ? "bn" : "en",
    client: "web",
    context: {
      productId: typeof body.context?.productId === "string" ? body.context.productId : undefined,
      cartIds: Array.isArray(body.context?.cartIds) ? body.context.cartIds.filter((x) => typeof x === "string").slice(0, 50) : undefined,
    },
  };

  if (!ASSISTANT_API) {
    return new Response(demoStream(clean, Boolean(req.headers.get("authorization"))), { headers: SSE_HEADERS });
  }

  let res: Response;
  try {
    res = await fetch(ASSISTANT_API + "/assistant/chat", {
      method: "POST",
      headers: { ...upstreamHeaders(req, false), "Content-Type": "application/json" },
      body: JSON.stringify(clean),
      cache: "no-store",
      signal: req.signal, // shopper pressed Stop / closed the panel: abort upstream too
    });
  } catch {
    return Response.json({ message: "Assistant is unavailable right now.", code: "upstream" }, { status: 502 });
  }
  if (!res.ok || !res.body) {
    const text = await res.text().catch(() => "");
    return new Response(text || JSON.stringify({ message: "Assistant is unavailable right now." }), {
      status: res.status || 502,
      headers: { "Content-Type": "application/json" },
    });
  }
  return new Response(res.body, { headers: SSE_HEADERS });
}
