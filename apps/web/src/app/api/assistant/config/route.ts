// GET /api/assistant/config — greeting, suggested prompts, daily allowance.
import { DEMO_CONFIG } from "@/lib/assistant/demo";
import { ASSISTANT_API, forwardJson } from "@/lib/assistant/proxy";

export const dynamic = "force-dynamic";

export async function GET(req: Request) {
  if (!ASSISTANT_API) return Response.json(DEMO_CONFIG);
  return forwardJson(req, "/assistant/config", { method: "GET" });
}
