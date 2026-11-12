<?php

namespace App\Services\Payment;

use RuntimeException;

/**
 * Kegagalan komunikasi dengan gateway pembayaran.
 *
 * Dibedakan dari kesalahan aplikasi agar pemanggil dapat memutuskan untuk
 * mencoba lagi tanpa mengubah status booking.
 */
class PaymentGatewayException extends RuntimeException {}
