# AMQP 0-9-1

| 帧中文名                         | 帧归属        | 帧名                                     | 传输方向 |
|:-----------------------------|:-----------|:---------------------------------------|:----:|
| 启动                           | connection | Connection.Start                       | `<`  |
| 启动应答                         | connection | Connection.Start-Ok                    | `>`  |
| 安全挑战                         | connection | Connection.Secure                      | `<`  |
| 安全应答                         | connection | Connection.Secure-Ok                   | `>`  |
| 协商参数                         | connection | Connection.Tune                        | `<`  |
| 协商确认                         | connection | Connection.Tune-Ok                     | `>`  |
| 打开连接                         | connection | Connection.Open                        | `>`  |
| 打开连接确认                       | connection | Connection.Open-Ok                     | `<`  |
| 关闭连接                         | connection | Connection.Close                       | `<>` |
| 关闭连接确认                       | connection | Connection.Close-Ok                    | `<>` |
| 打开通道                         | channel    | Channel.Open                           | `>`  |
| 打开通道确认                       | channel    | Channel.Open-Ok                        | `<`  |
| 通道流控                         | channel    | Channel.Flow                           | `<>` |
| 通道流确认                        | channel    | Channel.Flow-Ok                        | `<>` |
| 关闭通道                         | channel    | Channel.Close                          | `<>` |
| 关闭通道确认                       | channel    | Channel.Close-Ok                       | `<>` |
| 访问请求                         | connection | Access.Request                         | `>`  |
| 访问确认                         | connection | Access.Request-Ok                      | `<`  |
| 交换器声明                        | channel    | Exchange.Declare                       | `>`  |
| 交换器声明确认                      | channel    | Exchange.Declare-Ok                    | `<`  |
| 交换器删除                        | channel    | Exchange.Delete                        | `>`  |
| 交换器删除确认                      | channel    | Exchange.Delete-Ok                     | `<`  |
| 交换器绑定                        | channel    | Exchange.Bind                          | `>`  |
| 交换器绑定确认                      | channel    | Exchange.Bind-Ok                       | `<`  |
| 交换器解绑                        | channel    | Exchange.Unbind                        | `>`  |
| 交换器解绑确认                      | channel    | Exchange.Unbind-Ok                     | `<`  |
| 队列声明                         | channel    | Queue.Declare                          | `>`  |
| 队列声明确认                       | channel    | Queue.Declare-Ok                       | `<`  |
| 队列绑定                         | channel    | Queue.Bind                             | `>`  |
| 队列绑定确认                       | channel    | Queue.Bind-Ok                          | `<`  |
| 队列解绑                         | channel    | Queue.Unbind                           | `>`  |
| 队列解绑确认                       | channel    | Queue.Unbind-Ok                        | `<`  |
| 队列清空                         | channel    | Queue.Purge                            | `>`  |
| 队列清空确认                       | channel    | Queue.Purge-Ok                         | `<`  |
| 队列删除                         | channel    | Queue.Delete                           | `>`  |
| 队列删除确认                       | channel    | Queue.Delete-Ok                        | `<`  |
| 基础发布（发布消息方法）                 | channel    | Basic.Publish                          | `>`  |
| 基础投递（服务器向消费者投递）              | channel    | Basic.Deliver                          | `<`  |
| 消费者注册                        | channel    | Basic.Consume                          | `>`  |
| 消费者注册确认                      | channel    | Basic.Consume-Ok                       | `<`  |
| 消费者取消                        | channel    | Basic.Cancel                           | `>`  |
| 消费者取消确认                      | channel    | Basic.Cancel-Ok                        | `<`  |
| 基础获取（拉取）                     | channel    | Basic.Get                              | `>`  |
| 基础获取确认（含消息）                  | channel    | Basic.Get-Ok                           | `<`  |
| 基础获取空（无消息）                   | channel    | Basic.Get-Empty                        | `<`  |
| 消息确认（ack）                    | channel    | Basic.Ack                              | `>`  |
| 消息拒绝（reject）                 | channel    | Basic.Reject                           | `>`  |
| 消息拒绝（nack，扩展）                | channel    | Basic.Nack (extension)                 | `>`  |
| 恢复未确认消息                      | channel    | Basic.Recover                          | `>`  |
| 恢复未确认消息确认                    | channel    | Basic.Recover-Ok                       | `<`  |
| 事务选择                         | channel    | Tx.Select                              | `>`  |
| 事务选择确认                       | channel    | Tx.Select-Ok                           | `<`  |
| 事务提交                         | channel    | Tx.Commit                              | `>`  |
| 事务提交确认                       | channel    | Tx.Commit-Ok                           | `<`  |
| 事务回滚                         | channel    | Tx.Rollback                            | `>`  |
| 事务回滚确认                       | channel    | Tx.Rollback-Ok                         | `<`  |
| 发布确认选择（publisher confirm）    | channel    | Confirm.Select                         | `>`  |
| 发布确认选择（publisher confirm）-ok | channel    | Confirm.Select-Ok                      | `<`  |
| 发布确认（ack/nack from broker）   | channel    | Confirm.Ack / Confirm.Nack (extension) | `<`  |
| 内容头帧（消息元数据）                  | channel    | Content-Header (class-specific)        | `<>` |
| 内容体帧（消息体分片）                  | channel    | Content-Body                           | `<>` |
| 心跳帧                          | connection | Heartbeat                              | `<>` |
