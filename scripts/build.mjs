import { build, context } from 'esbuild';
import { spawn } from 'node:child_process';
import { mkdirSync, copyFileSync } from 'node:fs';
const watch = process.argv.includes('--watch');
mkdirSync('public/assets/fonts', { recursive: true });
mkdirSync('public/assets/licenses', { recursive: true });
for (const name of ['inter', 'barlow-condensed']) copyFileSync(`node_modules/@fontsource/${name}/LICENSE`, `public/assets/licenses/${name}.txt`);
copyFileSync('node_modules/swiper/LICENSE', 'public/assets/licenses/swiper.txt');
for (const [family, weights] of [['inter', [400, 500, 600, 700]], ['barlow-condensed', [600, 700, 800]]]) {
  for (const weight of weights) copyFileSync(`node_modules/@fontsource/${family}/files/${family}-latin-${weight}-normal.woff2`, `public/assets/fonts/${family}-${weight}.woff2`);
}
const options = { entryPoints: ['resources/js/app.js'], outfile: 'public/assets/js/app.js', bundle: true, format: 'esm', minify: !watch, target: ['es2022'], legalComments: 'eof' };
if (watch) { const ctx = await context(options); await ctx.watch(); } else await build(options);
const args = ['node_modules/@tailwindcss/cli/dist/index.mjs', '-i', 'resources/css/app.css', '-o', 'public/assets/css/app.css', ...(watch ? ['--watch'] : ['--minify'])];
const child = spawn(process.execPath, args, { stdio: 'inherit' });
child.on('exit', code => { if (!watch) process.exit(code ?? 1); });
for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => { child.kill(signal); process.exit(); });
