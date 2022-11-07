<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb86ec637313980c3ab39e5d77f4b945d
{
    public static $prefixLengthsPsr4 = array (
        'W' => 
        array (
            'WCPOS\\WooCommercePOS\\' => 21,
        ),
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'WCPOS\\WooCommercePOS\\' => 
        array (
            0 => __DIR__ . '/../..' . '/includes',
        ),
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'Firebase\\JWT\\BeforeValidException' => __DIR__ . '/..' . '/firebase/php-jwt/src/BeforeValidException.php',
        'Firebase\\JWT\\ExpiredException' => __DIR__ . '/..' . '/firebase/php-jwt/src/ExpiredException.php',
        'Firebase\\JWT\\JWK' => __DIR__ . '/..' . '/firebase/php-jwt/src/JWK.php',
        'Firebase\\JWT\\JWT' => __DIR__ . '/..' . '/firebase/php-jwt/src/JWT.php',
        'Firebase\\JWT\\Key' => __DIR__ . '/..' . '/firebase/php-jwt/src/Key.php',
        'Firebase\\JWT\\SignatureInvalidException' => __DIR__ . '/..' . '/firebase/php-jwt/src/SignatureInvalidException.php',
        'WCPOS\\WooCommercePOS\\API' => __DIR__ . '/../..' . '/includes/API.php',
        'WCPOS\\WooCommercePOS\\API\\Auth' => __DIR__ . '/../..' . '/includes/API/Auth.php',
        'WCPOS\\WooCommercePOS\\API\\Controller' => __DIR__ . '/../..' . '/includes/API/Controller.php',
        'WCPOS\\WooCommercePOS\\API\\Customers' => __DIR__ . '/../..' . '/includes/API/Customers.php',
        'WCPOS\\WooCommercePOS\\API\\Orders' => __DIR__ . '/../..' . '/includes/API/Orders.php',
        'WCPOS\\WooCommercePOS\\API\\Payment_Gateways' => __DIR__ . '/../..' . '/includes/API/Payment_Gateways.php',
        'WCPOS\\WooCommercePOS\\API\\Products' => __DIR__ . '/../..' . '/includes/API/Products.php',
        'WCPOS\\WooCommercePOS\\API\\Settings' => __DIR__ . '/../..' . '/includes/API/Settings.php',
        'WCPOS\\WooCommercePOS\\API\\Stores' => __DIR__ . '/../..' . '/includes/API/Stores.php',
        'WCPOS\\WooCommercePOS\\API\\Taxes' => __DIR__ . '/../..' . '/includes/API/Taxes.php',
        'WCPOS\\WooCommercePOS\\Activator' => __DIR__ . '/../..' . '/includes/Activator.php',
        'WCPOS\\WooCommercePOS\\Admin' => __DIR__ . '/../..' . '/includes/Admin.php',
        'WCPOS\\WooCommercePOS\\Admin\\Menu' => __DIR__ . '/../..' . '/includes/Admin/Menu.php',
        'WCPOS\\WooCommercePOS\\Admin\\Notices' => __DIR__ . '/../..' . '/includes/Admin/Notices.php',
        'WCPOS\\WooCommercePOS\\Admin\\Permalink' => __DIR__ . '/../..' . '/includes/Admin/Permalink.php',
        'WCPOS\\WooCommercePOS\\Admin\\Plugins' => __DIR__ . '/../..' . '/includes/Admin/Plugins.php',
        'WCPOS\\WooCommercePOS\\Admin\\Products' => __DIR__ . '/../..' . '/includes/Admin/Products.php',
        'WCPOS\\WooCommercePOS\\Admin\\Settings' => __DIR__ . '/../..' . '/includes/Admin/Settings.php',
        'WCPOS\\WooCommercePOS\\Deactivator' => __DIR__ . '/../..' . '/includes/Deactivator.php',
        'WCPOS\\WooCommercePOS\\Gateways' => __DIR__ . '/../..' . '/includes/Gateways.php',
        'WCPOS\\WooCommercePOS\\Gateways\\Card' => __DIR__ . '/../..' . '/includes/Gateways/Card.php',
        'WCPOS\\WooCommercePOS\\Gateways\\Cash' => __DIR__ . '/../..' . '/includes/Gateways/Cash.php',
        'WCPOS\\WooCommercePOS\\Init' => __DIR__ . '/../..' . '/includes/Init.php',
        'WCPOS\\WooCommercePOS\\Logger' => __DIR__ . '/../..' . '/includes/Logger.php',
        'WCPOS\\WooCommercePOS\\Orders' => __DIR__ . '/../..' . '/includes/Orders.php',
        'WCPOS\\WooCommercePOS\\Products' => __DIR__ . '/../..' . '/includes/Products.php',
        'WCPOS\\WooCommercePOS\\Status' => __DIR__ . '/../..' . '/includes/Status.php',
        'WCPOS\\WooCommercePOS\\Templates' => __DIR__ . '/../..' . '/includes/Templates.php',
        'WCPOS\\WooCommercePOS\\Templates\\Frontend' => __DIR__ . '/../..' . '/includes/Templates/Frontend.php',
        'WCPOS\\WooCommercePOS\\Templates\\Pay' => __DIR__ . '/../..' . '/includes/Templates/Pay.php',
        'WCPOS\\WooCommercePOS\\Traits\\Settings' => __DIR__ . '/../..' . '/includes/Traits/Settings.php',
        'WCPOS\\WooCommercePOS\\i18n' => __DIR__ . '/../..' . '/includes/i18n.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb86ec637313980c3ab39e5d77f4b945d::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb86ec637313980c3ab39e5d77f4b945d::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb86ec637313980c3ab39e5d77f4b945d::$classMap;

        }, null, ClassLoader::class);
    }
}
