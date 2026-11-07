import { NextResponse } from "next/server";
import { jobStatus } from "@/lib/data";

// Browser-facing proxy for fetch-job status (keeps the API origin and internal key on the server).
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^[\w-]{6,64}$/.test(id)) return NextResponse.json({ status: "unknown" }, { status: 400 });
  const s = await jobStatus(id);
  return NextResponse.json(s, { headers: { "Cache-Control": "no-store" } });
}
