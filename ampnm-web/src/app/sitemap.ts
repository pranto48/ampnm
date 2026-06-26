import { MetadataRoute } from "next";

export default function sitemap(): MetadataRoute.Sitemap {
  const baseUrl = "https://ampnm.itsupport.com.bd";
  const routes = [
    "",
    "/pricing",
    "/products",
    "/solutions",
    "/services",
    "/about",
    "/download",
    "/docs",
    "/changelog",
    "/contact",
  ];

  return routes.map((route) => ({
    url: `${baseUrl}${route}`,
    lastModified: new Date().toISOString(),
    changeFrequency: route === "" ? "daily" : "weekly",
    priority: route === "" ? 1.0 : route === "/pricing" || route === "/products" ? 0.9 : 0.7,
  }));
}
