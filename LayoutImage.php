<?php
class LayoutImage 
{
    private $layout; // Stores the layout image resource.
    private $layoutInfo; // Array containing the essential layout image information.
    private $images = array(); // Stores an array of images to be embedded to the layout.


    public function __construct($layoutImage = '', $size = array()) 
    {
        if (!function_exists('imagecreatefrompng')) return; // GD is not available.
        
        // Load the layout image.
        $this->setImage($layoutImage, 'layout', $size);
    }
    
    public function __destruct()
    {
        // A little bit of garbage collection to free up memory.
        imagedestroy($this->layout);
        foreach((array)$this->images as $image) {
            imagedestroy($image);
        }
    }
    
    /**
     * Method to render and show the image.
     */
    public function render() {
        if (!$this->layout) return false;
        
        $mime = $this->layoutInfo['mime'] ? $this->layoutInfo['mime'] : 'image/png';
        
        header('Content-Type: ' . $mime);
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($this->layout);
                break;
            case 'image/gif':
                imagegif($this->layout);
            case 'image/png':
            default:
                imagepng($this->layout);
                break;
        }
    }
    
    /**
     * Method to save the image to a file.
     */
    public function save($path) {
        if (!$this->layout || !$path) return false;
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        switch ($extension) {
            case 'png':
                return imagepng($this->layout, $path);
                break;
            case 'jpeg':
            case 'jpg':
                return imagejpeg($this->layout, $path);
                break;
            case 'gif':
                return imagegif($this->layout, $path);
                break;
            default:
                break;
        }
        
        return false;
    }
    
    /** 
     * Method to get the image from a given path and store it as a class resource.
     * 
     * @param string $image Image file path.
     * @param string $type Image type can be 'layout' or just 'image'.
     */
    public function setImage($image, $type = 'image', $size = array(800, 600)) 
    {        
        if (file_exists($image) && is_readable($image)) {
            $info = $this->_getImageInfo($image);
            
            switch ($info['mime']) {
                case 'image/png':
                    $img = imagecreatefrompng($image);
                    break;
                case 'image/jpeg':
                    $img = imagecreatefromjpeg($image);
                    break;
                case 'image/gif':
                    $old = imagecreatefromgif($image);
                    $img = imagecreatetruecolor($info[0], $info[1]);
                    imagecopy($image, $old, 0, 0, 0, 0, $info[0], $info[1]);
                    break;
                default:
                    break;
            }
        }

        switch ($type) {
            case 'image':
                if ($img) $this->images[] = $img;
                break;
            case 'layout':
                if ($img) {
                    $this->layout = $img;
                    $this->layoutInfo = $info;
                } else {
                    $this->layout = imagecreatetruecolor($size[0], $size[1]);
                    $gray = imagecolorallocate($this->layout, 128, 128, 128);
                    imagefilledrectangle($this->layout, 0, 0, $size[0], $size[1], $gray);
                    $this->layoutInfo = $this->_getImageInfo($this->layout);
                }
                break;
            default:
                break;
        }
    }
    
    /**
     * Method to embed text in a layout image.
     * 
     * @param string $text Text to embed.
     * @param string $font Font file path (should be a TTF font).
     * @param array $position Coordinates to position the text.
     * @param int $size Font size.
     * @param $int $margin Text margins.
     */
    public function setText($text, $font, $position = array('top', 'left'), $size = 10, $margin = 0)
    {
        if (!$text && !$font && (!is_array($position) || count($position) != 2)) return; // TODO: Much better to throw an exception than just this check.
        
        $textArea = $this->_getWidth($this->layout) - ($margin * 2); // Consider both left and right edges.
        $lines = $this->_wrapText($text, $font, $size, $textArea);
        
        $position = array_map('strtolower', $position);
        // Reverse the lines if text is to be positioned at the bottom.
        if (in_array('bottom', $position)) {
            $lines = array_reverse($lines);
        }

        $lineHeight = 0;
        foreach ($lines as $line) {
            $bbox = imagettfbbox($size, 0, $font, $line);
            
            $x = null; 
            $y = null;
            foreach ($position as $index => $value) {
                if (is_numeric($value)) {
                    if (0 == $index) {
                        $x = $value + $margin;
                    } else {
                        $y = $value + $bbox[1] + $margin - ($bbox[5] / 2) + $lineHeight;
                    }
                } else {
                    switch ($value) {
                        case 'right':
                            $x = floor($this->_getWidth($this->layout) - $bbox[2]) - $margin;
                            break;
                        case 'left':
                            $x = $margin;
                            break;                        
                        case 'center':
                            if ((0 == $index && !('left' == $position[1] || 'right' == $position[1])) || !empty($y)) {
                                $x = $bbox[0] + ($this->_getWidth($this->layout) / 2) - ($bbox[4] / 2);    
                            } else {
                                $y = $bbox[1] + ($this->_getHeight($this->layout) / 2) - ($bbox[5] / 2) + $lineHeight;
                            }
                            break;
                        case 'bottom':
                            $y = $this->_getHeight($this->layout) - ($bbox[1] + $margin - ($bbox[5] / 2) + $lineHeight);
                            break;
                        case 'top':
                            $y = $bbox[1] + $margin - ($bbox[5] / 2) + $lineHeight;
                            break;
                        default:
                            // TODO: Maybe throw another exception here.
                            break;
                    }
                }
            }
            $lineHeight += $bbox[1] - $bbox[7] + 5; // Set the line height for the next line of text.
                       
            $black = imagecolorallocate($this->layout, 0, 0, 0);
            imagettftext($this->layout, $size, 0, $x, $y, $black, $font, $line);
        }       
    }
    
    public function embedImage($embedImage, $position = array()) 
    {
        // TODO: Probably can be done here later or refactored to another class by itself.
        // Call getImage($embedImageFilePath) to get the embedded image resource.
        // Determine the margins appropriate for the layout and embedded image's sizes (embedded image can be center-aligned so some calculations must be made).
        // Embed the image to the layout.
    }
    
    /**
     * Helper method to get an image's relevant information.
     * 
     * @param resource Image resource.
     * @return array Returns an array of information relevant to the image resource.
     */
    private function _getImageInfo($image)
    {
        return getimagesize($image); // This function doesn't only return the image size but some essential information as well.
    }
    
    /**
     * Helper method that explodes a text into an array of lines given a layout width.
     * 
     * @param string $text The text to wrap around.
     * @param string $font Font file path (should be a TTF font).
     * @param int $size Font size.
     * @param int $width Width of the container image, in this case the layout.
     * @param $int $angle Rotation angle.
     * @return array Returns an array of lines.
     */
    private function _wrapText($text, $font, $size, $width, $angle = 0)
    {
        $lines = array();
        $words = explode(' ', $text); // TODO: How about for line breaks or tabs.
        
        if (count($words)) {
            $currentLine = 0;
            $lines[$currentLine] = array_shift($words);
            foreach ($words as $word) {
                $line = imagettfbbox($size, $angle, $font, $lines[$currentLine] . ' ' . $word);
                if ($line[2] - $line[0] < $width) {
                    $lines[$currentLine] .= ' ' . $word;
                } else {
                    ++$currentLine;
                    $lines[$currentLine] = $word;
                }
            }
        }

        return $lines;
    }
    
    /**
     * Helper method to get the width of an image.
     * 
     * @param resource Image resource.
     * @return int Returns the image width.
     */
    private function _getWidth($image)
    {
        return imagesx($image);
    }
    
    /**
     * Helper method to get the height of an image.
     * 
     * @param resource Image resource.
     * @return int Returns the image height.
     */
    private function _getHeight($image)
    {
        return imagesy($image);
    }
}