# Вклад в Triangle Media

[English version](CONTRIBUTING.md)

Спасибо, что помогаете улучшать плагин Triangle Media.

Перед отправкой изменений прочитайте [Кодекс поведения](CODE_OF_CONDUCT_ru.md), [политику безопасности](SECURITY.ru.md) и [документацию проекта](README.ru.md).

## Локальные проверки

Нужны PHP 8.1 или новее и расширения `curl`, `fileinfo`, `imagick`. Выполните:

```sh
composer validate --strict --no-check-publish --no-interaction
composer run check --no-interaction
composer audit --locked --no-interaction
```

Изменения загрузки, получения изображений по URL, путей хранения и фильтров должны сопровождаться регрессионным тестом. Не добавляйте в pull request учётные данные, production-данные и сгенерированные изображения.

## Pull request

Не расширяйте scope без необходимости, описывайте влияние на совместимость и указывайте команды с результатами проверок. GitHub Actions запускает PHP 8.1 и 8.5 с минимальными и последними допустимыми зависимостями.

Общие вопросы задавайте в GitHub Issues. О возможной уязвимости сообщайте приватно через функцию GitHub **Report a vulnerability**; не публикуйте подробности эксплуатации в issue.
