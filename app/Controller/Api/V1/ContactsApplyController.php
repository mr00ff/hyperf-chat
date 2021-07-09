<?php

namespace App\Controller\Api\V1;

use App\Cache\FriendApply;
use App\Cache\Repository\LockRedis;
use App\Service\UserService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\JWTAuthMiddleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Di\Annotation\Inject;
use App\Service\ContactApplyService;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ContactsApplyController
 * @Controller(prefix="/api/v1/contacts/apply")
 * @Middleware(JWTAuthMiddleware::class)
 *
 * @package App\Controller\Api\V1
 */
class ContactsApplyController extends CController
{
    /**
     * @Inject
     * @var ContactApplyService
     */
    private $service;

    /**
     * @RequestMapping(path="create", methods="post")
     * @param UserService $userService
     * @return ResponseInterface
     */
    public function create(UserService $userService)
    {
        $params = $this->request->inputs(['friend_id', 'remark']);
        $this->validate($params, [
            'friend_id' => 'required|integer',
            'remark'    => 'present|max:50'
        ]);

        $params['friend_id'] = (int)$params['friend_id'];

        $user = $userService->findById($params['friend_id']);
        if (!$user) {
            return $this->response->fail('用户不存在！');
        }

        $user_id = $this->uid();
        $key     = "{$user_id}_{$params['friend_id']}";
        if (LockRedis::getInstance()->lock($key, 10)) {
            $isTrue = $this->service->create($user_id, $params['friend_id'], $params['remark']);
            if ($isTrue) {
                return $this->response->success([], '发送好友申请成功...');
            } else {
                LockRedis::getInstance()->delete($key);
            }
        }

        return $this->response->fail('添加好友申请失败！');
    }

    /**
     * @RequestMapping(path="accept", methods="post")
     * @return ResponseInterface
     */
    public function accept()
    {
        $params = $this->request->inputs(['apply_id', 'remark']);
        $this->validate($params, [
            'apply_id' => 'required|integer',
            'remark'   => 'present|max:20'
        ]);

        $user_id = $this->uid();
        $isTrue  = $this->service->accept($user_id, intval($params['apply_id']), $params['remark']);
        if (!$isTrue) {
            return $this->response->fail('处理失败！');
        }

        return $this->response->success([], '处理成功...');
    }

    /**
     * @RequestMapping(path="decline", methods="post")
     * @return ResponseInterface
     */
    public function decline()
    {
        $params = $this->request->inputs(['apply_id', 'remark']);
        $this->validate($params, [
            'apply_id' => 'required|integer',
            'remark'   => 'present|max:20'
        ]);

        $isTrue = $this->service->decline($this->uid(), intval($params['apply_id']), $params['remark']);
        if (!$isTrue) {
            return $this->response->fail('处理失败！');
        }

        return $this->response->success([], '处理成功...');
    }


    /**
     * 获取联系人申请未读数
     * @RequestMapping(path="records", methods="get")
     *
     * @return ResponseInterface
     */
    public function records()
    {
        $params = $this->request->inputs(['page', 'page_size']);
        $this->validate($params, [
            'page'      => 'present|integer',
            'page_size' => 'present|integer'
        ]);

        $page      = $this->request->input('page', 1);
        $page_size = $this->request->input('page_size', 10);
        $user_id   = $this->uid();

        FriendApply::getInstance()->rem(strval($user_id));

        return $this->response->success(
            $this->service->getApplyRecords($user_id, $page, $page_size)
        );
    }
}