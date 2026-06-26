import type { Metadata } from "next";
import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import { Providers } from "./providers";
import { MainLayoutWrapper } from "@/components/main-layout-wrapper";

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
    default: "AMPNM Portal - Client & Licensing SaaS",
    template: "%s | AMPNM Portal",
  },
  description: "Enterprise administrative console for client accounts, licensing node keys management, and dynamic payment configurations.",
  metadataBase: new URL("https://portal.itsupport.com.bd"),
  keywords: [
    "AMPNM Portal",
    "AMPNM Logins",
    "IT Support BD Portal",
    "SaaS Administrative Console",
    "Client Licensing Directory"
  ],
  alternates: {
    canonical: "/",
  },
  openGraph: {
    title: "AMPNM Portal - Client & Licensing SaaS",
    description: "Enterprise administrative console for client accounts, licensing node keys management, and dynamic payment configurations.",
    url: "https://portal.itsupport.com.bd",
    siteName: "AMPNM Portal",
    locale: "en_US",
    type: "website",
  },
  twitter: {
    card: "summary",
    title: "AMPNM Portal - Client & Licensing SaaS",
    description: "Enterprise administrative console for client accounts, licensing node keys management, and dynamic payment configurations.",
  },
  robots: {
    index: false, // Disallow search engines from index-crawling the administrative authentication gates globally
    follow: true,
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
      <body className="h-full bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-50">
        <Providers>
          <MainLayoutWrapper>{children}</MainLayoutWrapper>
        </Providers>
      </body>
    </html>
  );
}
