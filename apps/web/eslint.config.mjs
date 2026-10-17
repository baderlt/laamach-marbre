import { defineConfig, globalIgnores } from 'eslint/config';
import typescript from 'typescript-eslint';
import hooks from 'eslint-plugin-react-hooks';

export default defineConfig([
  ...typescript.configs.recommended,
  { plugins: { 'react-hooks': hooks }, rules: hooks.configs.recommended.rules },
  globalIgnores(['.next*/**', 'next-env.d.ts']),
]);
