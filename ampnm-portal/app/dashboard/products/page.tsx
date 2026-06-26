"use client";

import { useMonitorStore } from "@/store/use-monitor-store";
import { Package, Check, Tag } from "lucide-react";
import { cn } from "@/lib/utils";

export default function ProductsPage() {
  const { products } = useMonitorStore();

  return (
    <div className="space-y-8">
      {/* Product Catalog Header */}
      <div>
        <h2 className="text-3xl font-bold tracking-tight text-zinc-950 dark:text-zinc-50">
          Products Catalog (GMEN)
        </h2>
        <p className="text-zinc-500 dark:text-zinc-400 text-sm">
          Overview of software license products, feature boundaries, and monthly subscription tiers.
        </p>
      </div>

      {/* Products Grid */}
      <div className="grid gap-8 md:grid-cols-2 lg:grid-cols-3">
        {products.map((product) => {
          const isCluster = product.id === "prod-cluster";
          return (
            <div
              key={product.id}
              className={cn(
                "relative bg-white dark:bg-zinc-900 rounded-2xl border p-8 flex flex-col justify-between shadow-sm hover:shadow-md transition-all duration-200",
                isCluster 
                  ? "border-blue-500 dark:border-blue-500 ring-2 ring-blue-500/10 dark:ring-blue-500/5 scale-105" 
                  : "border-zinc-200 dark:border-zinc-800"
              )}
            >
              {isCluster && (
                <span className="absolute -top-3.5 left-1/2 -translate-x-1/2 bg-blue-600 text-white text-[10px] uppercase font-bold tracking-widest px-3 py-1 rounded-full shadow">
                  Most Popular
                </span>
              )}

              <div>
                <div className="flex items-center gap-3">
                  <div className={cn(
                    "p-2 rounded-lg w-fit",
                    isCluster 
                      ? "bg-blue-100 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400" 
                      : "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400"
                  )}>
                    <Package size={20} />
                  </div>
                  <h3 className="font-bold text-lg text-zinc-900 dark:text-zinc-50">{product.name}</h3>
                </div>

                <div className="mt-6 flex items-baseline gap-1">
                  <span className="text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-zinc-50">
                    ${product.price}
                  </span>
                  <span className="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                    / {product.billingPeriod}
                  </span>
                </div>

                <div className="h-px bg-zinc-200 dark:bg-zinc-800 my-6" />

                {/* Features list */}
                <ul className="space-y-4 text-sm" aria-label={`Features of ${product.name}`}>
                  {product.features.map((feature, idx) => (
                    <li key={idx} className="flex items-start gap-2.5 text-zinc-600 dark:text-zinc-400 font-medium">
                      <div className="mt-0.5 p-0.5 bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-full flex-shrink-0">
                        <Check size={12} />
                      </div>
                      <span>{feature}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="mt-8 pt-4 border-t border-zinc-100 dark:border-zinc-800/60">
                <div className="flex items-center gap-2 text-xs font-bold text-zinc-400 uppercase tracking-wider">
                  <Tag size={12} />
                  <span>ID: {product.id}</span>
                </div>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
