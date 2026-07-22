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
  title: "SaaS Licensing Pricing Packages",
  description: "Detailed features comparison matrix of Standard, Docker Cluster Pack, and Enterprise Core unlimited licensing plans.",
  alternates: {
    canonical: "/pricing",
  }
};

export default function PricingLayout({ children }: { children: React.ReactNode }) {
  return <>{children}</>;
}
