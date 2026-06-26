"use client";

import { useEffect } from "react";
import { logger } from "@/lib/logger";
import { ShieldAlert, RotateCcw } from "lucide-react";

export default function GlobalError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    logger.error("Root-level layout error caught", error, {
      context: "GlobalErrorBoundary",
      extra: { digest: error.digest },
    });
  }, [error]);

  return (
    <html lang="en">
      <body className="flex flex-col items-center justify-center min-h-screen bg-zinc-50 dark:bg-zinc-950 font-sans p-6">
        <div className="flex flex-col items-center justify-center p-8 text-center bg-white dark:bg-zinc-900 rounded-xl border border-red-500/30 shadow-2xl max-w-lg w-full">
          <div className="p-4 bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 rounded-full mb-4">
            <ShieldAlert size={48} className="animate-bounce" />
          </div>
          <h1 className="text-2xl font-bold tracking-tight text-zinc-900 dark:text-zinc-50 mb-2">
            Critical System Failure
          </h1>
          <p className="text-zinc-500 dark:text-zinc-400 text-sm max-w-md mb-6">
            The network monitoring portal encountered a critical global rendering error. The platform operations team has been notified.
          </p>
          <div className="w-full text-left bg-zinc-50 dark:bg-zinc-950 p-4 rounded-md font-mono text-xs overflow-auto max-h-[200px] border border-zinc-200 dark:border-zinc-800 text-red-500 mb-6">
            {error.message || "Fatal layout rendering issue"}
          </div>
          <button
            onClick={() => reset()}
            className="inline-flex items-center justify-center gap-2 px-5 py-3 bg-red-600 text-white font-medium text-sm rounded-lg shadow hover:bg-red-500 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-red-600"
          >
            <RotateCcw size={16} />
            Reboot Platform
          </button>
        </div>
      </body>
    </html>
  );
}
