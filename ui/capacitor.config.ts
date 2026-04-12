import type { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.pharsayo.app',
  appName: 'PharSayo',
  webDir: 'dist',
  plugins: {
    CapacitorHttp: {
      enabled: true,
    },
  },
  android: {
    /** Use repo-root `android/` so there is only one native project (avoids stale `ui/android`). */
    path: '../android',
    allowMixedContent: true,
  },
};

export default config;
