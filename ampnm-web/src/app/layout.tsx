import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";
import { ThemeProvider } from "@/components/ThemeProvider";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: {
    default: "AMPNM - Advanced Node Monitoring Platform",
    template: "%s | AMPNM - Advanced Node Monitoring Platform",
  },
  description: "Secure multi-tenant software licensing agent, Docker cluster monitor, and active telemetry analytics systems developed by IT Support BD.",
  metadataBase: new URL("https://ampnm.itsupport.com.bd"),
  keywords: [
    "AMPNM",
    "Node Monitoring",
    "Docker Agent",
    "SaaS Licensing",
    "IT Support BD",
    "Sayed Arif",
    "Telemetry Dashboard",
    "Software License Guard",
    "Bangladeshi Telemetry Platform"
  ],
  authors: [{ name: "IT Support BD", url: "https://itsupport.com.bd" }],
  creator: "IT Support BD",
  publisher: "IT Support BD",
  alternates: {
    canonical: "/",
  },
  openGraph: {
    title: "AMPNM - Advanced Node Monitoring Platform",
    description: "Secure multi-tenant software licensing agent, Docker cluster monitor, and active telemetry analytics systems developed by IT Support BD.",
    url: "https://ampnm.itsupport.com.bd",
    siteName: "AMPNM",
    locale: "en_US",
    type: "website",
  },
  twitter: {
    card: "summary_large_image",
    title: "AMPNM - Advanced Node Monitoring Platform",
    description: "Secure multi-tenant software licensing agent, Docker cluster monitor, and active telemetry analytics systems developed by IT Support BD.",
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      "max-video-preview": -1,
      "max-image-preview": "large",
      "max-snippet": -1,
    },
  },
  icons: {
    icon: "/favicon.ico",
  }
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html
      lang="en"
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased`}
      suppressHydrationWarning
    >
      <head>
        <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><style>@keyframes spin{from{transform-origin:32px 32px;transform:rotate(0deg)}to{transform-origin:32px 32px;transform:rotate(360deg)}}@keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}.ring{animation:spin 3s linear infinite}.dot{animation:pulse 1.5s ease-in-out infinite}</style></defs><circle cx='32' cy='32' r='28' fill='%230f172a' stroke='%2306b6d4' stroke-width='3'/><circle cx='32' cy='32' r='18' fill='none' stroke='%2322d3ee' stroke-width='1.5' stroke-dasharray='8 4' class='ring'/><circle cx='32' cy='32' r='9' fill='%2306b6d4'/><circle cx='32' cy='32' r='5' fill='%230f172a' class='dot'/><circle cx='32' cy='11' r='3' fill='%2322d3ee' class='dot'/><circle cx='53' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:.5s'/><circle cx='11' cy='43' r='3' fill='%2322d3ee' class='dot' style='animation-delay:1s'/></svg>" />
        <script
          dangerouslySetInnerHTML={{
            __html: `
              try {
                if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                  document.documentElement.classList.add('dark');
                } else {
                  document.documentElement.classList.remove('dark');
                }
              } catch (_) {}
            `,
          }}
        />
      </head>
      <body className="min-h-full flex flex-col font-sans selection:bg-blue-500/30 selection:text-blue-200">
        <ThemeProvider>
          <Navbar />
          <main className="flex-1 flex flex-col">{children}</main>
          <Footer />
        </ThemeProvider>
      </body>
    </html>
  );
}

