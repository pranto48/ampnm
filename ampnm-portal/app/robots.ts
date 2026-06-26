import { MetadataRoute } from "next";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: ["/", "/login", "/register"],
      disallow: ["/dashboard/", "/api/"],
    },
    sitemap: "https://portal.itsupport.com.bd/sitemap.xml",
  };
}
