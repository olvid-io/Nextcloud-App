import { createAppConfig } from "@nextcloud/vite-config";
import { join, resolve } from "path";

export default createAppConfig(
  {
    main: resolve(join("src", "main.js")),
    adminSettings: resolve(join("src", "adminSettings.js")),
  },
  {
    createEmptyCSSEntryPoints: true,
    extractLicenseInformation: true,
    thirdPartyLicense: false,
  }
);
