type LogLevel = "DEBUG" | "INFO" | "WARN" | "ERROR";

interface LogOptions {
  context?: string;
  extra?: Record<string, unknown>;
}

class Logger {
  private isServer = typeof window === "undefined";

  private formatMessage(level: LogLevel, message: string, options?: LogOptions) {
    const timestamp = new Date().toISOString();
    const env = this.isServer ? "Server" : "Client";
    const contextStr = options?.context ? ` [${options.context}]` : "";
    return `[${timestamp}] [${env}] [${level}]${contextStr}: ${message}`;
  }

  debug(message: string, options?: LogOptions) {
    if (process.env.NODE_ENV === "production") return;
    const formatted = this.formatMessage("DEBUG", message, options);
    console.debug(formatted, options?.extra || "");
  }

  info(message: string, options?: LogOptions) {
    const formatted = this.formatMessage("INFO", message, options);
    console.log(formatted, options?.extra || "");
  }

  warn(message: string, options?: LogOptions) {
    const formatted = this.formatMessage("WARN", message, options);
    console.warn(formatted, options?.extra || "");
  }

  error(message: string, error?: Error | unknown, options?: LogOptions) {
    const errorDetails = error instanceof Error 
      ? { name: error.name, message: error.message, stack: error.stack }
      : error;

    const formatted = this.formatMessage("ERROR", message, options);
    console.error(formatted, {
      ...options?.extra,
      error: errorDetails,
    });

    // SaaS integration hook: send client-side errors via Beacon API in production
    if (!this.isServer && process.env.NODE_ENV === "production") {
      try {
        const payload = JSON.stringify({
          timestamp: new Date().toISOString(),
          level: "ERROR",
          message,
          error: errorDetails,
          context: options?.context,
        });
        navigator.sendBeacon("/api/logs/report", payload);
      } catch (e) {
        // Fallback silently if sendBeacon fails
      }
    }
  }
}

export const logger = new Logger();
export default logger;
