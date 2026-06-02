import { defineConfig, type UserConfig } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

// Vite-plus injects custom parameters. We extend the type so TS remains calm.
interface VitePlusConfig extends UserConfig {
  staged?: Record<string, string>;
  fmt?: Record<string, unknown>;
  lint?: {
    jsPlugins?: Array<{ name: string; specifier: string }>;
    rules?: Record<string, string>;
    options?: {
      typeAware?: boolean;
      typeCheck?: boolean;
    };
  };
}

export default defineConfig({
  staged: {
    "*": "vp check --fix",
  },
  fmt: {},
  lint: {
    jsPlugins: [{ name: "vite-plus", specifier: "vite-plus/oxlint-plugin" }],
    rules: { "vite-plus/prefer-vite-plus-imports": "error" },
    options: { typeAware: true, typeCheck: true },
  },
  plugins: [
    laravel({
      input: ["resources/css/app.css", "resources/js/app.js", "resources/js/passkeys.js"],
      refresh: true,
      fonts: [
        bunny("Instrument Sans", {
          weights: [400, 500, 600],
        }),
      ],
    }),
    tailwindcss(),
  ],
  server: {
    cors: true,
    watch: {
      ignored: ["**/storage/framework/views/**"],
    },
  },
} as VitePlusConfig);
