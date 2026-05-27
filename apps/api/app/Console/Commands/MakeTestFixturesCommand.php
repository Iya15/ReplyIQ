<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Font;

/**
 * Generates binary test fixtures (sample.pdf, sample.docx) in tests/fixtures/.
 * Run once, commit the output. Re-run only if fixture content needs to change.
 */
class MakeTestFixturesCommand extends Command
{
    protected $signature = 'make:test-fixtures';

    protected $description = 'Generate sample.txt, sample.pdf, and sample.docx test fixtures';

    // ASCII-only content — keeps the hand-crafted PDF stream encoding simple.
    private const CONTENT = <<<'TEXT'
        ReplyIQ Help Center

        Getting Started

        ReplyIQ is an AI-powered chatbot platform that helps businesses automate customer support. Once you sign up, you can create your first chatbot in under five minutes by navigating to the dashboard and clicking New Chatbot.

        Your chatbot can be trained on any combination of documents, web pages, and manual text entries. The knowledge base powers all responses. The more high-quality content you add, the better your chatbot performs.

        Key Features

        ReplyIQ supports PDF, DOCX, and plain text uploads. Web page crawling is available on the Pro plan and above. All knowledge base content is processed asynchronously and ready within a few minutes of uploading.

        Frequently Asked Questions

        Q: How do I update my chatbot's knowledge base?
        A: Navigate to the chatbot's Knowledge tab, click Add Document, and upload your file. Updates are processed automatically and go live within two minutes.

        Q: Can I use ReplyIQ on multiple websites?
        A: Yes. Each chatbot has a unique embed code. You can add the same chatbot to as many domains as you specify in the Embed settings.
        TEXT;

    public function handle(): int
    {
        $dir = base_path('tests/fixtures');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = implode("\n\n", array_map(
            'trim',
            explode("\n\n", trim(self::CONTENT)),
        ));

        $this->writeTxt($dir, $content);
        $this->writePdf($dir, $content);
        $this->writeDocx($dir, $content);

        $this->info('Fixtures written to tests/fixtures/');

        return self::SUCCESS;
    }

    private function writeTxt(string $dir, string $content): void
    {
        file_put_contents("{$dir}/sample.txt", $content."\n");
        $this->line('  ✓ sample.txt');
    }

    private function writePdf(string $dir, string $content): void
    {
        // Hand-crafted minimal PDF-1.4. Computes xref offsets exactly.
        // smalot/pdfparser reads object streams regardless of visual layout,
        // so text overflowing the page is still fully extracted.
        $lines = explode("\n", $content);

        $stream = "BT\n/F1 11 Tf\n14 TL\n72 720 Td\n";
        foreach ($lines as $line) {
            $line = trim($line);
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
            $stream .= "({$escaped}) Tj T*\n";
        }
        $stream .= "ET\n";

        $objs = [
            1 => "<</Type /Catalog /Pages 2 0 R /Info 6 0 R>>",
            2 => "<</Type /Pages /Kids [3 0 R] /Count 1>>",
            3 => "<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources <</Font <</F1 5 0 R>>>>>>",
            4 => "<</Length ".strlen($stream).">>\nstream\n".$stream."endstream",
            5 => "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>",
            6 => "<</Title (ReplyIQ Help Center)>>",
        ];

        $raw = "%PDF-1.4\n";
        $offsets = [];

        for ($i = 1; $i <= 6; $i++) {
            $offsets[$i] = strlen($raw);
            $raw .= "{$i} 0 obj\n".$objs[$i]."\nendobj\n";
        }

        $xrefOffset = strlen($raw);
        $raw .= "xref\n0 7\n";
        $raw .= "0000000000 65535 f \n";

        for ($i = 1; $i <= 6; $i++) {
            $raw .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        $raw .= "trailer\n<</Size 7 /Root 1 0 R /Info 6 0 R>>\n";
        $raw .= "startxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents("{$dir}/sample.pdf", $raw);
        $this->line('  ✓ sample.pdf');
    }

    private function writeDocx(string $dir, string $content): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $paragraphs = explode("\n\n", $content);

        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') {
                continue;
            }

            // Q:/A: pairs: add as separate lines within one text run to preserve structure
            if (str_starts_with($para, 'Q:')) {
                $lines = explode("\n", $para);
                $textRun = $section->addTextRun();
                foreach ($lines as $idx => $line) {
                    $textRun->addText(trim($line));
                    if ($idx < count($lines) - 1) {
                        $textRun->addTextBreak();
                    }
                }
            } else {
                $section->addText($para);
            }
        }

        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save("{$dir}/sample.docx");
        $this->line('  ✓ sample.docx');
    }
}
