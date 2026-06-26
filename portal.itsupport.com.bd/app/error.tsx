"use client";

import { useEffect } from "react";
import { logger } from "@/lib/logger";
import { AlertTriangle, RotateCcw } from "lucide-react";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    logger.error("Dashboard page-level error caught", error, {
      context: "RouteErrorBoundary",
      extra: { digest: error.digest },
    });
  }, [error]);

  return (
    <div className="flex flex-col items-center justify-center min-h-[400px] p-8 text-center bg-white dark:bg-zinc-950 rounded-xl border border-red-500/20 shadow-xl max-w-lg mx-auto my-12">
      <div className="p-4 bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-full mb-4">
        <AlertTriangle size={40} className="animate-pulse" />
      </div>
      <h2 className="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-50 mb-2">
        Dashboard View Failure
      </h2>
      <p className="text-zinc-500 dark:text-zinc-400 text-sm max-w-md mb-6">
        An error occurred while loading this monitoring widget. The incident has been automatically logged for the system administrators.
      </p>
      <div className="w-full text-left bg-zinc-50 dark:bg-zinc-900 p-4 rounded-md font-mono text-xs overflow-auto max-w-full mb-6 max-h-[150px] border border-zinc-200 dark:border-zinc-800 text-zinc-600 dark:text-zinc-300">
        {error.message || "Unknown rendering exception"}
      </div>
      <button
        onClick={() => reset()}
        className="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-zinc-900 text-zinc-50 dark:bg-zinc-50 dark:text-zinc-900 font-medium text-sm rounded-lg shadow hover:bg-zinc-800 dark:hover:bg-zinc-200 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-zinc-950 dark:focus-visible:ring-zinc-300"
      >
        <RotateCcw size={16} />
        Retry View Loader
      </button>
    </div>
  );
}
