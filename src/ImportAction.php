<?php

namespace WWCCSVImporter;

/**
 * Interface ImportAction
 *
 * This interface represent an action to perform to change a particular field of a product.
 *
 * @package WWCCSVImporter
 */
interface ImportAction
{
    public function perform();
    public function pretend();
    public function getTargetId();
    public function getId();
}
