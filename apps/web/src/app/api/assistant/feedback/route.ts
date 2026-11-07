// POST /api/assistant/feedback { messageId, rating: "up"|"down" } — thumbs on an answer.
import { ASSISTANT_API, forwardJson } from "@/lib/assistant/proxy";

export async function POST(req: Request) {
  const b = (await req.json().catch(() => ({}))) as { messageId?: unknown; rating?: unknown };
  if (typeof b.messageId !== "number" || (b.rating !== "up" && b.rating !== "down")) return Response.json({ message: "Invalid feedback" }, { status: 422 });
  if (!ASSISTANT_API) return Response.json({ ok: true, demo: true });
  return forwardJson(req, "/assistant/feedback", { method: "POST", body: JSON.stringify({ messageId: b.messageId, rating: b.rating }) });
}
