<?php

declare(strict_types=1);

class ErrorHandler {
	public static function handler(Throwable $exception): void {
		file_put_contents(
			BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log',
			$exception->getMessage().PHP_EOL.print_r($exception->getTrace(), true)
		);
	}
}
