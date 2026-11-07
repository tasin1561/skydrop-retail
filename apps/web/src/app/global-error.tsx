"use client";

// Last-resort screen if the root layout itself fails. Renders its own document (no global CSS).
export default function GlobalError({ retry }: { error: unknown; retry: () => void }) {
  return (
    <html lang="en">
      <body style={{ margin: 0, fontFamily: "system-ui, sans-serif", background: "#f7f7f4", color: "#0b0b0f" }}>
        <title>Skydrop Retail</title>
        <main style={{ maxWidth: 420, margin: "0 auto", padding: "64px 16px", textAlign: "center" }}>
          <h1 style={{ fontSize: 24, margin: "0 0 8px" }}>We&apos;ll be right back</h1>
          <p style={{ color: "#475569", fontSize: 14 }}>Skydrop Retail hit a temporary problem. Please try again.</p>
          <button
            type="button"
            onClick={() => retry()}
            style={{ marginTop: 20, minHeight: 44, padding: "0 20px", borderRadius: 12, border: 0, background: "#5b3df5", color: "#fff", fontWeight: 700 }}
          >
            Try again
          </button>
        </main>
      </body>
    </html>
  );
}
