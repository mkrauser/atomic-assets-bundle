const fs = require("node:fs");
const path = require("node:path");
const { defaultGetLocalIdent } = require("css-loader");
const webpack = require("webpack");

const fixtures = [
  {
    name: "base64-default-length",
    localIdentName: "[hash:base64]",
    modulePath: "styles/button.module.css",
    localName: "button",
  },
  {
    name: "base64-multiple-tiers",
    localIdentName: "[hash:base64:40]",
    modulePath: "styles/button.module.css",
    localName: "button",
  },
  {
    name: "hex-multiple-tiers",
    localIdentName: "[hash:hex:40]",
    modulePath: "styles/button.module.css",
    localName: "button",
  },
  {
    name: "salted-base64",
    localIdentName: "[hash:base64:20]",
    modulePath: "styles/button.module.css",
    localName: "button",
    hashSalt: "fixture-salt",
  },
  {
    name: "windows-path",
    localIdentName: "[hash:base64:20]",
    modulePath: "styles\\button.module.css",
    localName: "button",
  },
];

const context = path.resolve("/virtual/project");
const results = fixtures.map((fixture) => {
  const normalizedModulePath = fixture.modulePath.replace(/\\/g, "/");
  const resourcePath = path.join(context, ...normalizedModulePath.split("/"));
  const loaderContext = {
    resourcePath,
    utils: { createHash: webpack.util.createHash },
    _compilation: {
      getPath: (template, data) =>
        template
          .replace(/\[contenthash\]/g, data.contentHash)
          .replace(/\[fullhash\]/g, data.chunk.hash),
    },
  };
  const expected = defaultGetLocalIdent(
    loaderContext,
    fixture.localIdentName,
    fixture.localName,
    {
      context,
      hashSalt: fixture.hashSalt,
      hashFunction: "md4",
      hashDigest: "hex",
      hashDigestLength: 20,
      hashStrategy: "resource-path-and-local-name",
    },
  );

  return { ...fixture, expected };
});
const output = {
  generatedBy: {
    cssLoader: require("css-loader/package.json").version,
    webpack: require("webpack/package.json").version,
  },
  fixtures: results,
};

fs.writeFileSync(
  path.join(__dirname, "hashes.json"),
  `${JSON.stringify(output, null, 2)}\n`,
);
