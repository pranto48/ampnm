/*
 * Copyright (c) IT Support BD. All rights reserved.
 * This file is part of AMPNM.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License...
 * (Commercial licenses available at https://ampnm.itsupport.com.bd/pricing)
 */
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
