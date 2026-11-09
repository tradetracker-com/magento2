<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Test\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * A PSR-7 reason phrase may not contain CR or LF, and `Response::__construct` throws
 * `InvalidArgumentException` when it does.
 *
 * A common construction in API clients:
 *
 *     } catch (GuzzleException $exception) {
 *         $response = $this->responseFactory->create([
 *             'status' => $exception->getCode(),
 *             'reason' => $exception->getMessage()
 *         ]);
 *     }
 *
 * A Guzzle client exception message is multi-line — it appends an excerpt of the response body —
 * so every 4xx and 5xx from the platform turned into a fatal thrown *out of the catch block that
 * was supposed to handle it*. The merchant saw a white screen or a critical log entry instead of
 * "the API rejected this", and the module looked broken rather than the request.
 *
 * The fix is a sanitiser. This test is the net that keeps it: a raw exception message may never
 * be handed to the response factory again, in this module or a module scaffolded from it.
 *
 * @see \TradeTracker\Connect\Service\Api\Request::reasonPhrase()
 */
class ReasonPhraseTest extends TestCase
{
    /**
     * Matches a `'reason' => …` array entry and captures what it is set to.
     */
    private const REASON_ENTRY = "/'reason'\s*=>\s*([^,\]\n]+)/";

    /**
     * The defect's signature: an exception message reaching the reason phrase unflattened.
     *
     * Matching on what is wrong rather than allowlisting what is right, because the right answer
     * takes more than one shape — `$this->reasonPhrase($e->getMessage())` at the call site, or a
     * `$reason` assigned from it a few lines earlier — and an allowlist rejects the second one.
     */
    private const SANITISERS = [
        'reasonPhrase(',
    ];

    public function testNoRawExceptionMessageIsUsedAsAReasonPhrase(): void
    {
        $problems = [];

        foreach ($this->modulePhpFiles() as $file) {
            $contents = (string)file_get_contents($file);

            if (!preg_match_all(self::REASON_ENTRY, $contents, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($matches[1] as [$expression, $offset]) {
                $expression = trim($expression);

                if (!str_contains($expression, 'getMessage()')) {
                    continue;
                }

                foreach (self::SANITISERS as $sanitiser) {
                    if (str_contains($expression, $sanitiser)) {
                        continue 2;
                    }
                }

                $problems[] = sprintf(
                    '%s:%d — %s',
                    $file,
                    substr_count(substr($contents, 0, $offset), "\n") + 1,
                    $expression
                );
            }
        }

        $this->assertSame(
            [],
            $problems,
            "A PSR-7 reason phrase must be sanitised — CR/LF in it throws InvalidArgumentException"
            . " from inside the catch block that was meant to handle the error:\n- "
            . implode("\n- ", $problems)
        );
    }

    /**
     * @return string[]
     */
    private function modulePhpFiles(): array
    {
        $moduleDir = dirname(__DIR__, 3);
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($moduleDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $path = $file->getPathname();
            $relative = substr($path, strlen($moduleDir));

            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($relative, '/Test/') || str_contains($relative, '/vendor/')) {
                continue;
            }

            $files[] = $path;
        }

        sort($files);

        return $files;
    }
}
