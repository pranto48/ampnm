import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Navbar } from "@/components/Navbar";
import { Footer } from "@/components/Footer";

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
      className={`${geistSans.variable} ${geistMono.variable} h-full antialiased dark`}
    >
      <body className="min-h-full flex flex-col bg-zinc-950 text-zinc-50 font-sans selection:bg-blue-500/30 selection:text-blue-200">
        <Navbar />
        <main className="flex-1 flex flex-col">{children}</main>
        <Footer />
      </body>
    </html>
  );
}

