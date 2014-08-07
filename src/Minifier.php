<?php

/**
 * JavaScript and CSS minifier/compressor tool
 *
 * In order to minimize the number and size of HTTP requests for JS/CSS content,
 * this script combines multiple JS/CSS files into a single file and compresses it.
 *
 * @author Chandra Shekhar <shekharsharma705@gmail.com>
 * @package Minifier
 * @version 1.1
 * @throws MinifyException
 */
class Minifier {

    const TYPE_JS  = 'js';
    const TYPE_CSS = 'css';
    const BASE_DIR = '../assets/';
    const TS_FILE  = '../etc/timestamp';

    /**
     * Identifier for which minifier has to be used.
     * CSS or JS ?
     *
     * @var string
     */
    protected $type;

    /**
     * Internal buffer for storing minified file contents
     *
     * @var string
     */
    protected $buffer;

    /**
     * Flag to confirm if timestamp has to be appended to filename or not?
     *
     * @var bool
     */
    protected $appendTS;

    /**
     * EPoch timestamp value
     *
     * @var string
     */
    protected $timestamp;

    /**
     * Input Directory path to pick original files
     *
     * @var string
     */
    protected $inputDir;

    /**
     * Array of file names which have to be minified
     *
     * @var string[]
     */
    protected $inputFiles;

    /**
     * Output Directory path to write minified version of files
     *
     * @var string
     */
    protected $outputDir;

    /** Output file name of minified version of file
     *
     * @var string
     */
    protected $outputFile;

    /**
     * Flag for verbose mode
     *
     * @var boolean
     */
    protected $isVerbose;

    /**
     * Constructor for Minifier class.<br>
     * Sets default value of various parameters
     */
    public function __construct() {
        $this->isVerbose  = true;
        $this->buffer     = '';
        $this->appendTS   = true;
        $this->timestamp  = time();
        $this->outputFile = 'main';
    }

    /**
     * Initialize input/output directory path for further use.<br>
     * TODO: make dir paths more configurable (app level config)
     */
    protected function setInOutDirs() {
        switch ($this->type) {
            case self::TYPE_CSS :
                $this->inputDir  = self::BASE_DIR . 'css/';
                $this->outputDir = self::BASE_DIR . 'livecss/';
                break;

            case self::TYPE_JS :
                $this->inputDir  = self::BASE_DIR . 'js/';
                $this->outputDir = self::BASE_DIR . 'livejs/';
                break;

            default:
                break;
        }
    }

    /**
     * Set minifier type
     *
     * @param string $type
     * @return Minifier
     */
    public function setType($type) {
        $this->type = strtolower($type);
        return $this;
    }

    /**
     * Set flag for appending timestamp values in minified file
     *
     * @param bool $flag
     * @return Minifier
     */
    public function setAppendTS($flag) {
        $this->appendTS = $flag;
        return $this;
    }

    /**
     * Update current timestamp value
     *
     * @return Minifier
     */
    public function setTimestamp() {
        $this->timestamp = time();
        return $this;
    }

    /**
     * Set output file name for minified version
     *
     * @return Minifier
     */
    public function setOutputFile($prefix) {
        $this->outputFile = $prefix;
        return $this;
    }

    public function setVerbose($mode) {
        $this->isVerbose = $mode;
        return $this;
    }

    /**
     * Set input file list, which will be minified
     *
     * @return Minifier
     */
    public function setInputFiles(array $files) {
        if (!empty($files)) {
            $this->inputFiles = $files;
        }
        return $this;
    }

    /**
     * Minify JS/CSS resources.<br>
     * Reduces files size and combines various files into single.
     *
     * @return bool
     */
    public function minify() {
        try {
            if (!empty($this->type)) {
                $isMinified = '';
                $this->setInOutDirs();
                $this->setDefaultInputFiles();
                if (!empty($this->inputFiles) && is_array($this->inputFiles)) {
                    $this->prepareBuffer();
                    if ($this->type === self::TYPE_CSS) {
                        $isMinified = $this->minifyCSS();
                    } elseif ($this->type === self::TYPE_JS) {
                        $isMinified = $this->minifyJS();
                    }
                    if ($isMinified) {
                        if ($this->createMinifiedFile()) {
                            $this->updateTimestamp();
                            $this->log(strtoupper($this->type) . ' Minification: Successfully Done!!');
                        } else {
                            throw new MinifyException('Could not create minified file!');
                        }
                    }
                } else {
                    throw new MinifyException('Error: No input file given to minify!');
                }
            } else {
                throw new MinifyException('No minifier type defined. Exiting!');
            }
        } catch (MinifyException $e) {
            if ($this->isVerbose) {
                echo $e->getMessage() . PHP_EOL;
            }
        }
    }

    /**
     * Minify CSS files
     *
     * @return bool
     */
    protected function minifyCSS() {
        $this->buffer = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $this->buffer);
        $this->buffer = str_replace(': ', ':', $this->buffer);
        $this->buffer = str_replace(
            array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '),
            '',
            $this->buffer
        );
        return !empty($this->buffer);
    }

    /**
     * Minify JS files
     *
     * @return boolean
     */
    protected function minifyJS() {
        $this->buffer = preg_replace_callback('(
            (?:
            (^|[-+\([{}=,:;!%^&*|?~]|/(?![/*])|return|throw) # context before regexp
            (?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
            (/(?![/*])(?:
            \\\\[^\n]
            |[^[\n/\\\\]++
            |\[(?:\\\\[^\n]|[^]])++
        )+/) # regexp
            |(^
            |\'(?:\\\\.|[^\n\'\\\\])*\'
            |"(?:\\\\.|[^\n"\\\\])*"
            |([0-9A-Za-z_$]+)
            |([-+]+)
            |.
        )
        )(?:\s|//[^\n]*+\n|/\*(?:[^*]|\*(?!/))*+\*/)* # optional space
        )sx', function($match) {
            static $last = '';
            $match += array_fill(1, 5, null); // avoid E_NOTICE
            list(, $context, $regexp, $result, $word, $operator) = $match;
            if ($word != '') {
                $result = ($last == 'word' ? "\n" : ($last == 'return' ? " " : "")) . $result;
                $last = ($word == 'return' || $word == 'throw' || $word == 'break' ? 'return' : 'word');
            } elseif ($operator) {
                $result = ($last == $operator[0] ? "\n" : "") . $result;
                $last = $operator[0];
            } else {
                if ($regexp) {
                    $result = $context . ($context == '/' ? "\n" : "") . $regexp;
                }
                $last = '';
            }
            return $result;
        }, $this->buffer."\n");

        return !empty($this->buffer);
    }

    /**
     * Loads all the input files into memory and buffers all their content for
     * minification action.
     */
    protected function prepareBuffer() {
        $this->buffer = '';
        foreach ($this->inputFiles as $file) {
            $this->buffer .= @file_get_contents($this->inputDir . $file);
        }
    }

    /**
     * Writes combined minified string into file on disk
     *
     * @return number|boolean
     */
    protected function createMinifiedFile() {
        $this->setTimestamp();
        $tsStr = (empty($this->appendTS)) ? '' : '-' . $this->timestamp;
        $fileName = $this->outputFile . $tsStr . '.' . $this->type;
        if (!empty($this->buffer)) {
            $outputFilePath = $this->outputDir . $fileName;
            $created = @file_put_contents($outputFilePath, $this->buffer);
            return $created;
        }
        return false;
    }

    /**
     * Mark all the files of input directory for minification.<br>
     * This method is used for setting default, Its input file list can be
     * further modified as per requirement.
     *
     * @return Minifier
     */
    protected function setDefaultInputFiles() {
        $allFiles = array();
        $allfiles  = scandir($this->inputDir);
        if (!empty($allfiles)) {
            foreach ($allfiles as $index => $name) {
                $parts = explode('.', $name);
                $ext = end($parts);
                if (strtolower($ext) === strtolower($this->type)) {
                    $allFiles[] = $name;
                }
            }
        }
        $this->inputFiles = $allfiles;
        return $this;
    }

    /**
     * Write update value of timestamp in file<br>
     * Which is further used for identification of latest version of minified file
     */
    protected function updateTimestamp() {
        $oldTimestamp = '';
        $minKey = strtoupper($this->outputFile . '_' . $this->type);
        $themeContent = file_get_contents(self::TS_FILE);
        if (preg_match('/'.$minKey.'=(\w+)/', $themeContent, $result)) {
            $oldTimestamp = $result[1];
        } else {
            $this->log('No key exists with provided name!');
        }
        $themeContent = preg_replace(
            "/($minKey)=(\w+)/",
            "$minKey=$this->timestamp",
            $themeContent
        );
        if (file_put_contents(self::TS_FILE, $themeContent)) {
            $this->removeOldFiles($minKey, $oldTimestamp);
        }
    }

    /**
     * Deletes css files with old timestamp value
     *
     * @param string $key : JS/CSS file key
     * @param string $ts  : Old timestamp
     */
    protected function removeOldFiles($key, $ts) {
        $name = explode("_", $key);
        $name = strtolower($name[0] . '-' . $ts . '.' . end($name));
        $filePath = $this->outputDir . $name;
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->log("Old {$this->type} File Successfully deleted");
            }
        } else {
            throw new MinifyException("Oops!! Old <{$this->type}> File {$filePath} Could not be deleted");
        }
    }

    /**
     * echos the message if verbose mode is set
     *
     * @param string $msg
     */
    protected function log($msg) {
        if ($this->isVerbose) {
            echo $msg . PHP_EOL;
        }
    }
}

/**
 * Minification exception class
 */
class MinifyException extends Exception {}