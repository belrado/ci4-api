<?php

namespace App\Filters;

use App\Models\MemberModel;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Firebase\JWT\JWT;
use Config\Services;
use Exception;

class JWTAuthFilter implements FilterInterface
{
    use ResponseTrait;

    public function before(RequestInterface $request, $arguments = null)
    {
        helper('jwt');

        $authHeader = $request->getServer('HTTP_AUTHORIZATION');
        try {
            $encodedToken = getJWTFromRequest($authHeader);
            validateJWTFromRequest($encodedToken);
            return $request;

        } catch (Exception $e) {
            return Services::response()
                ->setJSON(
                    [
                        'error_msg' => $e->getMessage(),
                    ]
                )
                ->setStatusCode(ResponseInterface::HTTP_UNAUTHORIZED);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do something here
    }
}
