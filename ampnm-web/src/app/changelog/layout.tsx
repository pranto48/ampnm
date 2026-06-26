import type { Metadata } from "next";

export const metadata: Metadata = {
  title: "Release Changelog & Version Histories",
  description: "Inspect release notes, patch log timeline releases, updates, and hotfixes for AMPNM licensing daemons and web interfaces.",
  alternates: {
    canonical: "/changelog",
  }
};

export default function ChangelogLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
