import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Contact Our System Helpdesk",
  description: "Contact system engineers at IT Support BD for custom licensing integrations, whitelists setups, or hotline queries.",
  alternates: {
    canonical: "/contact",
  }
};

export default function ContactLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
