"use client";

import {
  ArrowUpRight,
  BookOpen,
} from "lucide-react";

import { cn } from "@/lib/utils";

const EXTERNAL_CATALOGS = [
  {
    title: "Mann Filter",
    href: "https://catalog.mann-filter.com/EU/tur",
    accent: "from-[#f4d94e] via-[#d2b838] to-[#846f1c]",
    text: "text-[#15180b]",
  },
  {
    title: "Şampiyon",
    href: "http://www.sampiyonfilter.com.tr/%5CPGuest%5CUrunKoduIleArama.aspx",
    accent: "from-[#ff7b6e] via-[#c73932] to-[#6f1519]",
    text: "text-white",
  },
  {
    title: "Motoec",
    href: "http://www.motoec.com/Catalog.aspx",
    accent: "from-[#80d8ff] via-[#2f92cb] to-[#164b77]",
    text: "text-white",
  },
  {
    title: "Real",
    href: "http://www.garantifiltre.com/real.asp",
    accent: "from-[#ffb08f] via-[#e35c32] to-[#8c1d15]",
    text: "text-white",
  },
  {
    title: "Wunder",
    href: "http://katalog.wunderfilter.com/",
    accent: "from-[#9dbbff] via-[#536cdf] to-[#202d83]",
    text: "text-white",
  },
  {
    title: "Fleetguard",
    href: "https://catalog.cumminsfiltration.com/catalog/",
    accent: "from-[#f0b36c] via-[#c67724] to-[#6b360e]",
    text: "text-white",
  },
  {
    title: "Donaldson",
    href: "https://dynamic.donaldson.com/WebStore/search/cross_reference.html",
    accent: "from-[#bed7f3] via-[#6d95c0] to-[#334d6a]",
    text: "text-white",
  },
  {
    title: "Rixenberg",
    href: "http://catalog.rixenberg.com/tr/anasayfa.html",
    accent: "from-[#8f969f] via-[#3e4650] to-[#12161b]",
    text: "text-white",
  },
] as const;

export function CatalogsPage() {
  return (
    <div className="mx-auto w-full max-w-[1480px] space-y-4 text-slate-100">
      <section id="marka-kataloglari" className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {EXTERNAL_CATALOGS.map((catalog) => (
          <a
            key={catalog.title}
            href={catalog.href}
            target="_blank"
            rel="noreferrer"
            className={cn(
              "group relative flex min-h-[158px] overflow-hidden rounded-[24px] border border-white/10 bg-gradient-to-br p-5 shadow-[0_22px_48px_-36px_rgba(0,0,0,0.72)] transition hover:-translate-y-1 hover:border-white/30",
              catalog.accent,
              catalog.text
            )}
          >
            <span className="absolute inset-x-0 top-0 h-px bg-white/45" />
            <span className="absolute -right-10 -top-12 h-36 w-36 rounded-full bg-white/18 blur-2xl transition group-hover:scale-125" />
            <span className="relative z-10 flex h-full w-full flex-col justify-between">
              <span className="flex items-center justify-between gap-3">
                <span className="flex h-13 w-13 items-center justify-center rounded-[18px] border border-white/20 bg-white/18 backdrop-blur">
                  <BookOpen className="h-6 w-6" />
                </span>
                <ArrowUpRight className="h-6 w-6 opacity-75 transition group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:opacity-100" />
              </span>
              <span>
                <strong className="block text-[1.65rem] font-black leading-none tracking-tight">{catalog.title}</strong>
                <span className="mt-3 inline-flex rounded-full border border-white/20 bg-white/16 px-3 py-1 text-xs font-black uppercase tracking-[0.1em]">
                  Kataloğu Aç
                </span>
              </span>
            </span>
          </a>
        ))}
      </section>
    </div>
  );
}
