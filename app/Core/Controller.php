<?php
declare(strict_types=1);

namespace App\Core;

/** सभी कंट्रोलर का आधार */
abstract class Controller
{
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return new Response(app('view')->render($template, $data), $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function toRoute(string $name, array $params = []): Response
    {
        return Response::redirect(route($name, $params));
    }

    protected function back(): Response
    {
        return Response::redirect(back_url());
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** नियम फ़ेल हों तो फ़ॉर्म पर त्रुटियों के साथ वापस */
    protected function validate(Request $request, array $rules, array $labels = []): array
    {
        $v = Validator::make($request->all(), $rules, $labels);
        if ($v->fails()) {
            throw new ValidationException($v->errors(), $request->post());
        }
        return $v->validated();
    }

    protected function authorize(string $permission): void
    {
        if (!can($permission)) {
            throw new HttpException(403);
        }
    }
}
