// POST /api/assistant/clear { conversationId } — "Clear conversation" (wipes the stored transcript).
import { ASSISTANT_API, forwardJson } from "@/lib/assistant/proxy";
import { isCrossSite } from "@/lib/clientIp";

export async function POST(req: Request) {
  if (isCrossSite(req)) return Response.json({ message: "Forbidden" }, { status: 403 });
  const b = (await req.json().catch(() => ({}))) as { conversationId?: unknown };
  const id = typeof b.conversationId === "string" && /^[0-9a-f-]{36}$/i.test(b.conversationId) ? b.conversationId : null;
  if (!id || !ASSISTANT_API) return Response.json({ ok: true });
  return forwardJson(req, `/assistant/conversations/${id}`, { method: "DELETE" });
}
