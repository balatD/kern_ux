# Security policy

## Reporting a vulnerability

Report privately, not in a public issue: open a
[security advisory](https://github.com/balatD/kern_ux/security/advisories/new) on the
repository. If that is not possible for you, write to the address on the maintainer's
GitHub profile and say in the subject line that it is a security report.

Please include what an attacker can do, the steps to reproduce it, and the versions of
this extension, TYPO3 and PHP you saw it on. A proof of concept helps and is not
required.

You will get an acknowledgement within a week. This is a community project maintained
by one person, so a fix may take longer than that — you will be told what is happening
rather than left waiting.

## What is in scope

Anything this package ships: the components, the content blocks, the `ext:form` theme,
the form templates, the page templates, the CLI commands and the styleguide middleware.

Two things deserve saying explicitly, because they are the parts that touch the network
or the filesystem:

- **`kern-ux:assets:install`** downloads the KERN distribution from the npm registry at
  install time. It pins the version, refuses redirects, verifies the published SHA-512
  integrity hash and extracts only stylesheets and font files. A way around any of those
  is a vulnerability here.
- **The styleguide middleware** serves a gallery of every component. It is off by
  default and is a development and audit tool. If it can be reached, or made to render
  something, on a site that has not enabled it, that is a vulnerability here.

## What is not in scope

- Vulnerabilities in TYPO3 itself — report those to
  [the TYPO3 security team](https://typo3.org/help/security-advisories).
- Vulnerabilities in the KERN UX distribution — report those to the
  [KERN project](https://www.kern-ux.de/).
- A site that has enabled `kernUx.styleguide.enable` in production. That is documented
  as a development tool; exposing it is a configuration decision, not a defect here.

## Supported versions

The extension is in beta, and only the latest release gets fixes. There is no
long-term-support line and it would be dishonest to imply one.
