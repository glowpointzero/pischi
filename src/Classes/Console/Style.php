<?php
namespace Glowpointzero\Pischi\Console;

class Style extends \Symfony\Component\Console\Style\SymfonyStyle
{
    protected $lineWidth = 80;
    protected $linePadding = '    ';

    const SAY_STYLE_DEFAULT = 'fg=white;bg=black';
    const SAY_STYLE_TITLE = 'fg=black;bg=cyan';
    const SAY_STYLE_SECTION = 'fg=black;bg=white';
    const SAY_STYLE_OK = 'fg=green;bg=black';
    const SAY_STYLE_SUCCESS = 'fg=black;bg=green';
    const SAY_STYLE_WARNING = 'fg=black;bg=yellow';
    const SAY_STYLE_ERROR = 'fg=black;bg=red';

    public function title($message)
    {
        $this->say(PHP_EOL . PHP_EOL . $message . PHP_EOL . PHP_EOL, self::SAY_STYLE_TITLE);
    }

    public function section($message)
    {
        $this->say(PHP_EOL . $message . PHP_EOL, self::SAY_STYLE_SECTION);
    }

    public function error($message)
    {
        $this->say(PHP_EOL . $message . PHP_EOL, self::SAY_STYLE_ERROR);
    }

    public function warning($message)
    {
        $this->say(PHP_EOL . $message . PHP_EOL, self::SAY_STYLE_WARNING);
    }

    public function caution($message)
    {
        $this->say(PHP_EOL . $message . PHP_EOL, self::SAY_STYLE_WARNING);
    }

    /**
     * Lets the user know a process has started.
     *
     * @param string $message
     */
    public function processingStart($message)
    {
        $lastCharacter = substr($message, -1);
        if ((!in_array($lastCharacter, ['.', '!', ' ', '?']))) {
            $message .= '... ';
        }
        $this->say($this->linePadding . $message, self::SAY_STYLE_DEFAULT, true, false);
    }

    /**
     * Lets the user know a previously started
     * process has ended successfully.
     *
     * @param string $message
     */
    public function processingEnd($message)
    {
        $this->say($message, self::SAY_STYLE_OK, true, false);
        $this->newLine();
    }

    /**
     * Outputs messages in different alert levels (styles),
     * inline or 'en bloc'. This is the raw(er) command for
     * warning() error(), etc.
     *
     * @param string $message
     * @param string $style
     * @param bool $inline
     * @param bool $addMargins
     */
    public function say($message, $style = self::SAY_STYLE_DEFAULT, $inline = false, $addMargins = true)
    {
        if ($style === null) {
            $style = self::SAY_STYLE_DEFAULT;
        }
        $lines = explode(PHP_EOL, wordwrap($message, $this->lineWidth, PHP_EOL, true));

        foreach ($lines as $lineNo => $line) {
            if (!$inline) {
                $line = str_pad(
                    $line,
                    $this->lineWidth-strlen($this->linePadding)*2
                );
                $line = $this->linePadding . $line . $this->linePadding;
            }

            $line = sprintf('<%s>%s</> ', $style, $line);
            $lines[$lineNo] = $line;
        }
        if ($addMargins) {
            array_unshift($lines, '');
            $lines[] = '';
        }
        $this->write($lines, !$inline);
    }
}
