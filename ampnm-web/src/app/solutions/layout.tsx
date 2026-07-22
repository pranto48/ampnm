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
  title: "Docker Cluster & Enterprise Solutions",
  description: "Secure compliance architectures whitelists setups, and multi-tenant telemetry dashboards for corporate server systems.",
  alternates: {
    canonical: "/solutions",
  }
};

export default function SolutionsLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
