RabbitMQ 多运行模式简介
------

### 软件安装

请参考官方文档安装，这里不再赘述。注意非 `root` 账号下安装可能存在某些依赖问题，可尝试切换 `root` 账号后安装。

```bash
#以下安装命令仅在 `Ubuntu 16.04.5 LTS` 环境下实验通过，其它版本或操作系统，请自行参考网络或官方文档安装
echo 'deb http://www.rabbitmq.com/debian/ testing main' | tee /etc/apt/sources.lis
t.d/rabbitmq.list
wget -O- https://www.rabbitmq.com/rabbitmq-release-signing-key.asc | apt-key add -
apt-get update
apt-get install -y rabbitmq-server

#查看服务状态：service rabbitmq-server status
#启动rabbitmq服务：service rabbitmq-server start
#停止rabbitmq服务：service rabbitmq-server stop
#后台启动：rabbitmq-server -detached
#运行状态：rabbitmqctl status

#查看所有的用户：rabbitmqctl list_users
#添加用户：rabbitmqctl add_user test test123    #username==>test    password==>test123
#授权用户：rabbitmqctl set_user_tags test administrator    #<==给用户 test 授予 administrator 权限
#         rabbitmqctl set_permissions -p / test ".*" ".*" ".*"
#删除用户：rabbitmqctl delete_user test
#修改密码：rabbitmqctl change_password test 123456

#查看已经安装的插件：cd /usr/lib/rabbitmq/    rabbitmq-plugins list
#开启网页版控制台：rabbitmq-plugins enable rabbitmq_management
#输入网址访问：http://localhost:15672/     使用上面设置的账号密码登录：test/test123
```

### 概念简介

1. `Server/Broker` : 接受客户端连接，实现AMQP消息队列和路由功能的进程。

2. `Virtual Host` : 其实是一个虚拟概念，类似于权限控制组，一个 `Virtual Host` 里面可以有若干个 `Exchange` 和 `Queue` ，但是权限控制的最小粒度是 `Virtual Host` 。

3. `Exchange` : 接受生产者发送的消息，并根据 `Binding` 规则将消息路由给服务器中的队列。`ExchangeType` 决定了 `Exchange` 路由消息的行为，例如，在 `RabbitMQ` 中，`ExchangeType` 有 `Direct` 、`Fanout` 和 `Topic` 等四种，不同类型的 `Exchange` 路由的行为是不一样的。

4. `Message Queue` : 消息队列，用于存储还未被消费者消费的消息。

5. `Message` : 由 `Header` 和 `Body` 组成，`Header` 是由生产者添加的各种属性的集合，包括 `Message` 是否被持久化、由哪个 `Message Queue` 接受、优先级是多少等。而 `Body` 是真正需要传输的应用数据。

6. `Binding` : `Binding` 联系了 `Exchange` 与 `Message Queue` 。`Exchange` 在与多个 `Message Queue` 发生 `Binding` 后会生成一张路由表，路由表中存储着 `Message Queue` 所需消息的限制条件即 `Binding Key` 。当 `Exchange` 收到 `Message` 时会解析其 `Header` 得到 `Routing Key` ，`Exchange` 根据 `Routing Key` 与 `Exchange Type` 将 `Message` 路由到 `Message Queue` 。`Binding Key` 由 `Consumer` 在 `Binding Exchange` 与 `Message Queue` 时指定，而 `Routing Key` 由 `Producer` 发送 `Message` 时指定，两者的匹配方式由 `Exchange Type` 决定。 

7. `Connection` : 连接，对于 `RabbitMQ` 而言，其实就是一个位于客户端和 `Broker` 之间的TCP连接。

8. `Channel` : 信道，仅仅创建了客户端到 `Broker` 之间的连接后，客户端还是不能发送消息的。需要为每一个 `Connection` 创建 `Channel` ，`AMQP` 协议规定只有通过`Channel` 才能执行 `AMQP` 的命令。一个 `Connection` 可以包含多个 `Channel`。之所以需要 `Channel` ，是因为TCP连接的建立和释放都是十分昂贵的，如果一个客户端每一个线程都需要与 `Broker` 交互，如果每一个线程都建立一个 `TCP` 连接，暂且不考虑 `TCP` 连接是否浪费，就算操作系统也无法承受每秒建立如此多的 `TCP` 连接。`RabbitMQ` 建议客户端线程之间不要共用 `Channel` ，至少要保证共用 `Channel` 的线程发送消息必须是串行的，但是建议尽量共用 `Connection`。

9. `Command` : `AMQP` 的命令，客户端通过 `Command` 完成与 `AMQP` 服务器的交互来实现自身的逻辑。例如在 `RabbitMQ` 中，客户端可以通过 `publish` 命令发送消息，`txSelect` 开启一个事务，`txCommit` 提交一个事务。

### 模式简介

`examples` 目录下存放各个模式的演示应用代码，复制 `config.php.example` 配置样例到 `config.php` 文件，自行修改其中的 `RabbitMQ` 配置信息。

```bash
git clone https://github.com/ycrao/rabbitmq-examples
cd rabbitmq-examples
composer install
cd examples
cp -r config.php.example config.php
vim config.php #修改配置
```

#### 简单模式[Simple Mode]

这种模式比较单一，单生产者单消费者。项目中使用 `yii-queue`，如果 `yii queue/listen` 只启动一个进程，可近似地认为它是一种简单模式。

代码示例：参考 `examples/simple` 目录下源码，执行下列命令：

```bash
#@terminal 1
php receiver.php
#@terminal 2
php sender.php
```

#### 工作队列模式[Worker Mode]

一个生产者，多个消费者，每个消费者获取到的消息唯一，多个消费者只有一个队列。

避免立即做一个资源密集型任务，必须等待它完成，而是把这个任务安排到稍后再做。我们将任务封装为消息并将其发送给队列。后台运行的工作进程将弹出任务并最终执行作业。当有多个 `worker` 同时运行时，任务将在它们之间共享。

项目中使用 `yii-queue`，如果 `yii queue/listen` 启动多个进程（可配合 `supervisor`）监听 ，可认为它是工作队列模式。

代码示例：参考 `examples/worker` 目录下源码，执行下列命令：

```bash
#@terminal 1
php worker1.php
#@terminal 2
php worker2.php
#@terminal 3
php send_task.php
```

#### 主动拉取模式[Pull Mode]

`RabbitMQ` 的消费者有两种模式，推模式（`Push`）和拉模式（`Pull`）。

推模式是最常用的，但是有些情况下推模式并不适用的，比如说：

- 由于某些限制，消费者在某个条件成立时才能消费消息
- 需要批量拉取消息进行处理

代码示例：参考 `examples/pull` 目录下源码，执行下列命令：

```bash
#@terminal 1
php pulled_consumer.php
#@terminal 2
php producer.php
```

#### 发布/订阅模式[Publish/Subscribe Mode]

流程：生产者者将消息首先发送到交换器，交换器绑定多个队列，然后被监听该队列的消费者所接收并消费。

生产者：可以将消息发送到队列或者是交换机。
消费者：只能从队列中获取消息。

>   特别注意：如果消息发送到没有队列绑定的交换机上，那么消息将丢失。交换机不能存储消息，消息存储在队列中。

在 `RabbitMQ` 中,交换器主要有四种类型: `direct`、 `fanout`、 `topic`, `headers`，这种模式下使用的交换器类型是 `fanout` 。

应用场景示例:

一个商城系统需要在管理员上传新的商品图片时，前台系统必须更新图片，日志系统必须记录相应的日志，那么就可以将两个队列绑定到图片上传交换器上，一个用于前台系统刚更新图片，另一个用于日志系统记录日志。

代码示例：参考 `examples/pubsub` 目录下源码，执行下列命令：

```bash
#@terminal 1
php subsciber1.php
#@terminal 2
php subsciber2.php
#@terminal 3
php publisher.php
```

#### 路由模式[Routing Mode]

发送端（生产者）按路由 `key` 发送消息，不同的接受端（消费者）按不同的路由 `key` 接受消息。注意：队列绑定到交换机时需要指定路由`key` 。

代码示例：参考 `examples/routing` 目录下源码，执行下列命令：

```bash
#@terminal 1
php routing_testing_consumer.php
#@terminal 2
php routing_production_consumer.php
#@terminal 3
php producer.php
```

#### 主题模式[Topic(s) Mode]

有些人称之为通配符模式，上面的路由模式是根据路由 `key` 进行完整的匹配（完全相等才发送消息），此模式是按字符串 `模糊匹配` 发送，接收端同样如此。

符号 "#" 表示匹配一个或多个词，符号 "*" 表示匹配一个词。

代码示例：参考 `examples/topic` 目录下源码，执行下列命令：

```bash
#@terminal 1
php topic_testing_consumer.php
#@terminal 2
php topic_production_consumer.php
#@terminal 3
php producer.php
```

#### 总结

`RabbitMQ` 提供了 `6` 种模式，分别是 `Simple`、 `Worker`（或称 `Work Queue`）、 `Publish/Subscribe`、 `Routing`、 `Topic(s)`、`RPC Request/Reply`。本文档详细讲述了前 `5` 种，并给出代码实现和思路（主动拉取模式属于消费端一种模式，不在此列，一般场景下均为推模式），其中 `Publish/Subscribe`、 `Routing` 与 `Topics` 三种模式可以统一归为 `Exchange` 模式，只是创建时交换机的类型不一样，分别是 `fanout`、 `direct` 与 `topic`。

### 参考资源

- [RabbitMQ官网](https://www.rabbitmq.com/)
- [php-amqplib](https://github.com/php-amqplib/php-amqplib)
- [RabbitMQ的几种典型使用场景](https://www.cnblogs.com/luxiaoxun/p/3918054.html)
- [RabbitMQ详解（三）------RabbitMQ的五种模式](https://www.cnblogs.com/Alva-mu/p/9535396.html)
- [rabbitmq官方的六种工作模式](https://blog.csdn.net/qq_33040219/article/details/82383127)
- [RabbitMQ笔记-Exchange 的几种模式](https://www.jianshu.com/p/19af0f40bbde)
- [RabbitMQ之RPC实现](https://blog.csdn.net/u013256816/article/details/55218595)