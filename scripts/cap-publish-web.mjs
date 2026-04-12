import { cpSync, existsSync, readdirSync, rmSync, statSync } from "fs";
import { dirname, join } from "path";
import { fileURLToPath } from "url";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const dist = join(root, "ui", "dist");
const pub = join(root, "public");

if (!existsSync(dist)) {
  console.error("Missing ui/dist — run: npm run build --prefix ui");
  process.exit(1);
}

const assetsOut = join(pub, "assets");
if (existsSync(assetsOut)) {
  rmSync(assetsOut, { recursive: true, force: true });
}
cpSync(join(dist, "assets"), assetsOut, { recursive: true });

for (const name of readdirSync(dist)) {
  const src = join(dist, name);
  if (!statSync(src).isFile()) continue;
  cpSync(src, join(pub, name));
}

console.log("Published ui/dist → public/ (kept api/, lang/, etc.)");
