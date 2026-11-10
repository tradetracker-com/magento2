/*
 * Copyright Magmodules.eu. All rights reserved.
 * See COPYING.txt for license details.
 */

import { execSync } from 'child_process';

/**
 * Quote a command so the shell that runs `docker exec` passes it through untouched.
 *
 * `execSync` hands its argument to `/bin/sh -c`, so a command wrapped in double quotes has every
 * `$name` expanded by that shell before the container ever sees it. For a PHP one-liner that means
 * `$env`, `$pdo` and friends silently become empty strings and the snippet does nothing — no error,
 * just a wrong answer. Single quotes stop all of it; the `'\''` dance is how a literal single quote
 * survives inside them.
 */
export function shellQuote(command: string): string {
  return `'${command.replace(/'/g, `'\\''`)}'`;
}

/**
 * A `docker exec` invocation whose command reaches the container verbatim.
 */
export function dockerExec(container: string, command: string, detached = false): string {
  return `docker exec ${detached ? '-d ' : ''}${container} bash -lc ${shellQuote(command)}`;
}

/**
 * Where Magento lives inside the container.
 *
 * Not the same everywhere: a local `bin/start` environment mounts it at /var/www/html, while the
 * magento2-in-a-box image CI and the VPS runner use puts it at /data. Hard-coding either one makes
 * the suite pass in one place and fail in the other for a reason that has nothing to do with the
 * module.
 */
export function magentoRoot(container: string): string {
  for (const candidate of ['/var/www/html', '/data']) {
    try {
      execSync(dockerExec(container, `test -f ${candidate}/bin/magento`), { stdio: 'pipe' });
      return candidate;
    } catch {
      // Not this one.
    }
  }

  throw new Error('Could not find bin/magento in the container; looked in /var/www/html and /data.');
}

/**
 * Where a module's own directory is inside the container.
 *
 * Deliberately probed rather than assumed, because it differs per environment and the difference
 * is invisible until a run fails: a local `bin/start` checkout mounts modules at
 * `/var/www/html/extensions/magmodules/<package>`, while the VPS runner copies them to
 * `/data/extensions/<package>` — no vendor segment — and a composer install puts them under
 * `vendor/magmodules/`. Hard-coding any one of those makes the suite pass in one place and fail
 * in another for a reason that has nothing to do with the module.
 *
 * @param container Docker container name.
 * @param pkg Composer package directory name, e.g. `magento2-feedbackcompany`.
 * @param marker A file that must exist inside the directory, to prove it is the right one.
 */
export function moduleDir(container: string, pkg: string, marker = 'composer.json'): string {
  const root = magentoRoot(container);

  // The VPS runner copies the module in as `<package>-<version>` (see e2e-run.sh: it rsyncs to
  // /tmp/e2e/$MODULE-$VERSION and docker-cp's that directory), so the name inside the container is
  // not always the package name. Try the suffixed spellings too rather than assume one layout.
  const names = [pkg, `${pkg}-249`, `${pkg}-248`, `${pkg}-247`];
  const candidates = names.flatMap((name) => [
    `${root}/extensions/magmodules/${name}`,
    `${root}/extensions/${name}`,
    `${root}/vendor/magmodules/${name}`,
  ]);

  for (const candidate of candidates) {
    try {
      execSync(dockerExec(container, `test -f ${candidate}/${marker}`), { stdio: 'pipe' });
      return candidate;
    } catch {
      // Not this one.
    }
  }

  throw new Error(
    `Could not find ${pkg} in the container. Looked in:\n  ${candidates.join('\n  ')}`
  );
}
