import configuration from '../../../../config/locales.json' with { type: 'json' };

export type Locale = { code: string; name: string; direction: 'ltr' | 'rtl' };
export type LocaleConfiguration = { default: string; fallback: string; locales: Locale[] };

export function validateLocales(value: unknown): LocaleConfiguration {
  if (!value || typeof value !== 'object') throw new Error('Invalid locale configuration');
  const input = value as Record<string, unknown>;
  if (!Array.isArray(input.locales) || input.locales.length === 0) throw new Error('At least one enabled locale is required');
  const codes = new Set<string>();
  const locales = input.locales.map((entry: unknown): Locale => {
    if (!entry || typeof entry !== 'object') throw new Error('Invalid locale entry');
    const locale = entry as Record<string, unknown>;
    if (typeof locale.code !== 'string' || !/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/.test(locale.code) || codes.has(locale.code.toLowerCase()) || typeof locale.name !== 'string' || !locale.name.trim() || locale.name.length > 80 || !['ltr', 'rtl'].includes(String(locale.direction))) {
      throw new Error('Invalid or duplicate locale entry');
    }
    codes.add(locale.code.toLowerCase());
    return { code: locale.code, name: locale.name, direction: locale.direction as Locale['direction'] };
  });
  for (const key of ['default', 'fallback']) {
    if (typeof input[key] !== 'string' || !locales.some(locale => locale.code === input[key])) throw new Error(`${key} must be an enabled locale`);
  }
  return { default: input.default as string, fallback: input.fallback as string, locales };
}

export const localeConfiguration = validateLocales(configuration);
export function findLocale(code: string) { return localeConfiguration.locales.find(locale => locale.code === code); }
export function localeForPath(path: string) { return findLocale(path.split('/')[1] || '') || findLocale(localeConfiguration.default)!; }
