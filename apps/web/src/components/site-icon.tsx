export function SiteIcon({ name = 'stone', className = '' }: { name?: string | null; className?: string }) {
  const paths: Record<string, React.ReactNode> = {
    brand: <><path d="m2 21 9-18 11 18h-5L11 11l-5 10M8 21l4-8 5 8M12 3l-2 4" /></>,
    stone: <><path d="m12 3 9 5v8l-9 5-9-5V8zM3 8l9 5 9-5M12 13v8" /></>,
    building: <><path d="M4 21V6l8-3 8 3v15M2 21h20M8 21v-5h8v5M8 8v3m4-3v3m4-3v3" /></>,
    ruler: <><path d="m3 16 13-13 5 5L8 21zM6 13l3 3m0-6 2 2m1-5 3 3m0-6 2 2" /></>,
    craft: <><path d="m4 20 8-8m1-9a6 6 0 0 0-5 8l-6 6a2 2 0 0 0 3 3l6-6a6 6 0 0 0 8-7l-4 4-3-3 4-4z" /></>,
    truck: <><path d="M3 5h12v12H3zM15 10h4l3 4v3h-7M5 17v1m13-1v1" /><circle cx="6" cy="18" r="2" /><circle cx="18" cy="18" r="2" /></>,
    check: <><path d="m12 2 8 3v6c0 5-8 11-8 11S4 16 4 11V5zM8 11l3 3 5-6" /></>,
    leaf: <><path d="M20 3C7 2 2 9 6 16s15 1 14-13ZM5 21 16 9" /></>,
    spark: <><path d="m12 2 3 6 7 1-5 5 1 8-6-4-6 4 1-8-5-5 7-1z" /></>,
    arrow: <path d="M4 12h16m-6-6 6 6-6 6" />,
    search: <><circle cx="10" cy="10" r="6" /><path d="m15 15 6 6" /></>,
    phone: <path d="m5 3 4 4-2 3c2 4 3 5 7 7l3-2 4 4-2 3C9 22 2 15 2 5z" />,
    mail: <><rect x="3" y="5" width="18" height="14" rx="1" /><path d="m3 6 9 7 9-7" /></>,
    pin: <><path d="M19 9c0 5-7 12-7 12S5 14 5 9a7 7 0 0 1 14 0Z" /><circle cx="12" cy="9" r="2" /></>,
    filter: <><path d="M3 6h18M3 12h18M3 18h18" /><circle cx="8" cy="6" r="2" /><circle cx="16" cy="12" r="2" /><circle cx="10" cy="18" r="2" /></>,
    menu: <path d="M3 6h18M3 12h18M3 18h18" />,
    close: <path d="m5 5 14 14M5 19 19 5" />,
  };
  return <svg className={`site-icon ${className}`} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{paths[name || 'stone'] || paths.stone}</svg>;
}
