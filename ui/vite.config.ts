import { defineConfig, loadEnv, type Plugin } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import { componentTagger } from "lovable-tagger";

/** Android WebView can fail to load same-origin ES modules when `crossorigin` is present on local assets. */
function stripCrossoriginOnLocalAssets(): Plugin {
  return {
    name: "strip-crossorigin-local-assets",
    apply: "build",
    transformIndexHtml: {
      order: "post",
      handler(html) {
        const strip = (tag: string) =>
          tag.replace(/\s+crossorigin(?:=["'][^"']*["'])?/gi, "");
        return html
          .replace(/<script\b[^>]*>/gi, (tag) =>
            tag.includes("./assets/") ? strip(tag) : tag
          )
          .replace(/<link\b[^>]*>/gi, (tag) =>
            tag.includes("./assets/") ? strip(tag) : tag
          );
      },
    },
  };
}

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), "");
  const backendOrigin = env.VITE_BACKEND_ORIGIN || "";
  const backendPublicPath = env.VITE_BACKEND_PUBLIC_PATH || "";

  return {
    base: "./",
    build: {
      outDir: "dist",
      emptyOutDir: true,
      /** Avoid extra preload requests that some WebViews mishandle. */
      modulePreload: false,
      /** Broader Android System WebView compatibility than default esnext. */
      target: "es2019",
    },
    server: {
      host: "::",
      port: 8080,
      hmr: {
        overlay: false,
      },
      ...(backendOrigin && {
        proxy: {
          "/api": {
            target: backendOrigin,
            changeOrigin: true,
            rewrite: (p) => `${backendPublicPath}${p}`,
          },
        },
      }),
    },
    plugins: [
      react(),
      stripCrossoriginOnLocalAssets(),
      mode === "development" && componentTagger(),
    ].filter(Boolean),
    resolve: {
      alias: {
        "@": path.resolve(__dirname, "./src"),
      },
      dedupe: [
        "react",
        "react-dom",
        "react/jsx-runtime",
        "react/jsx-dev-runtime",
        "@tanstack/react-query",
        "@tanstack/query-core",
      ],
    },
  };
});
