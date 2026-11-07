// https://docs.expo.dev/guides/using-eslint/
const { defineConfig } = require("eslint/config");
const expoConfig = require("eslint-config-expo/flat");

module.exports = defineConfig([
  expoConfig,
  {
    ignores: ["dist/*", ".expo/*", "node_modules/*", "scripts/*"],
  },
  {
    // HTML entity escaping does not apply to React Native <Text>.
    rules: { "react/no-unescaped-entities": "off" },
  },
]);
