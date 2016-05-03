<?php

interface ITimeable
{
    /**
	 * Retrieve the time taken up by this class
	 *
	 * @return float Execution time of the class since its instantiation (wall clock time)
	 */
    public function getExecutionTime();
}

?>
