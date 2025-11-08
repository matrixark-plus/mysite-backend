# Introduction

This is a skeleton application using the Hyperf framework. This application is meant to be used as a starting place for those looking to get their feet wet with Hyperf Framework.

# Requirements

Hyperf has some requirements for the system environment, it can only run under Linux and Mac environment, but due to the development of Docker virtualization technology, Docker for Windows can also be used as the running environment under Windows.

The various versions of Dockerfile have been prepared for you in the [hyperf/hyperf-docker](https://github.com/hyperf/hyperf-docker) project, or directly based on the already built [hyperf/hyperf](https://hub.docker.com/r/hyperf/hyperf) Image to run.

When you don't want to use Docker as the basis for your running environment, you need to make sure that your operating environment meets the following requirements:  

 - PHP >= 8.0
 - Any of the following network engines
   - Swoole PHP extension >= 4.5，with `swoole.use_shortname` set to `Off` in your `php.ini`
   - Swow PHP extension (Beta)
 - JSON PHP extension
 - Pcntl PHP extension
 - OpenSSL PHP extension （If you need to use the HTTPS）
 - PDO PHP extension （If you need to use the MySQL Client）
 - Redis PHP extension （If you need to use the Redis Client）
 - Protobuf PHP extension （If you need to use the gRPC Server or Client）

# Installation using Composer

The easiest way to create a new Hyperf project is to use [Composer](https://getcomposer.org/). If you don't have it already installed, then please install as per [the documentation](https://getcomposer.org/download/).

To create your new Hyperf project:

```bash
composer create-project hyperf/hyperf-skeleton path/to/install
```

If your development environment is based on Docker you can use the official Composer image to create a new Hyperf project:

```bash
docker run --rm -it -v $(pwd):/app composer create-project --ignore-platform-reqs hyperf/hyperf-skeleton path/to/install
```

# Getting started

Once installed, you can run the server immediately using the command below.

```bash
cd path/to/install
php bin/hyperf.php start
```

Or if in a Docker based environment you can use the `docker-compose.yml` provided by the template:

```bash
cd path/to/install
docker-compose up
```

This will start the cli-server on port `9501`, and bind it to all network interfaces. You can then visit the site at `http://localhost:9501/` which will bring up Hyperf default home page.

## Hints

- A nice tip is to rename `hyperf-skeleton` of files like `composer.json` and `docker-compose.yml` to your actual project name.
- Take a look at `config/routes.php` and `app/Controller/IndexController.php` to see an example of a HTTP entrypoint.

**Remember:** you can always replace the contents of this README.md file to something that fits your project description.

# 项目架构

本项目采用严格的MVC分层架构，主要包括以下层次：

## 1. 数据访问层 (Repository)

数据访问层负责所有数据库操作，通过Repository模式抽象化数据访问，主要组件包括：

- `App\Repository\UserRepository`：用户相关的数据访问操作

## 2. 业务逻辑层 (Service)

业务逻辑层封装所有业务逻辑，依赖于Repository层而不是直接使用模型：

- `App\Service\UserService`：用户管理服务
- `App\Service\PermissionService`：权限管理服务

## 3. 表现层 (Controller)

表现层处理HTTP请求和响应，调用Service层完成业务处理：

- `App\Controller\Api\AuthController`：认证相关接口
- `App\Controller\Api\PermissionController`：权限管理接口

## 4. 中间件 (Middleware)

中间件处理横切关注点，如认证和日志记录：

- `App\Middleware\JwtAuthMiddleware`：JWT认证中间件

## 5. 验证器 (Validator)

专门的验证器类处理请求参数验证：

- `App\Controller\Api\Validator\PermissionValidator`：权限相关参数验证

# 文档

项目相关文档位于`docs`目录：

- `docs/Architecture-Refactoring-Document.md`：架构重构文档
- `docs/API-Response-Guidelines.md`：API响应规范文档

详细的架构设计和重构过程请参考架构重构文档。
