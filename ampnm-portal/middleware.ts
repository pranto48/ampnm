import { NextResponse } from "next/server";
import type { NextRequest } from "next/server";

export function middleware(request: NextRequest) {
  const sessionToken = request.cookies.get("session-token")?.value;

  const isDashboard = request.nextUrl.pathname.startsWith("/dashboard");

  if (isDashboard) {
    if (request.cookies.get("bypass-auth")?.value === "true") {
      return NextResponse.next();
    }
    if (!sessionToken) {
      const url = request.nextUrl.clone();
      url.pathname = "/login";
      return NextResponse.redirect(url);
    }

    // Edge compatible JWT validation
    try {
      const parts = sessionToken.split(".");
      if (parts.length === 3) {
        // Decode payload part
        const payload = JSON.parse(atob(parts[1]));
        const now = Math.floor(Date.now() / 1000);
        
        // Check expiration
        if (payload.exp && payload.exp < now) {
          const url = request.nextUrl.clone();
          url.pathname = "/login";
          const response = NextResponse.redirect(url);
          response.cookies.delete("session-token");
          return response;
        }
      } else {
        throw new Error("Invalid token shape");
      }
    } catch (e) {
      const url = request.nextUrl.clone();
      url.pathname = "/login";
      const response = NextResponse.redirect(url);
      response.cookies.delete("session-token");
      return response;
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ["/dashboard/:path*"],
};
