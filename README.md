<p align="center"><a href="https://stellisoft.com" target="_blank"><img src="https://raw.githubusercontent.com/Stellify-Software-Ltd/stellify/refs/heads/main/public/stellify_logo.svg" width="200" alt="Stellify Logo"></a></p>

The current stable version of our Laravel web application framework, supercharged with Stellify.

## About Stellify

Stellify is a system of representing code as JSON data objects which are stored in a database tables rather than in files. These data objects can be assembled back to the code you originally authored to be interpreted on a server or sent to a browser for execution.

Here's an example of how we would store an HTML tag:

```
{
    type: "layout",
    tag: "div",
    id: "fbc60d5e-e9a2-4c0e-8d09-9a2d196d94fc",
    parent: "b00d4336-1898-4b10-b5f6-aa210ed97bc3",
    children: [
        "4efa4c77-5602-4372-8cf9-3419ada0cf6cm", 
        "71067e7b-7a61-43d0-97c5-1cb51c3ea262"
    ],
    classes: [
        "sm:mx-auto",
        "sm:w-full",
        "sm:max-w-sm",
        "text-white"
    ]
}
```

Here's how we would store a file:

```
{
    name: "SocialiteController",
    id: "d441b1e9-28b5-4144-8566-8b924394285a",
    type: "controller",
    extends: "71067e7b-7a61-43d0-97c5-1cb51c3ea262",
    methods: [
        "3e3ae425-4471-400e-ad36-dfeff487a85b"
    ],
    includes: [
        "6177293f-4c34-45d7-8e82-43291fd7c348"
    ]
}
```

It's important to note that additional fields exist in the same row, these fields tend to contain data we would wish to use in queries, such as `date_created`. You can view the table structures we use at Stellisoft in this repository, under the `database/migrations` directory.

And here's example of how we would store the token "for" in PHP: 

```
{
    type: "T_FOR",
    id: "23951891-1b84-4fa9-9f53-fe99faabf08e"
}
```

Finally, here's an example of how we would store a variable in Javascript:

```
{
    type: "variable",
    name: "counter",
    id: "0b270c57-3d26-4d89-be5a-91ee11cc5a79"
}
```
Stellify abstracts and standardises and your ***entire*** web application in this way, making it possible to pull updates in from a central remote store that update ***entire*** applications, all whilst remaining mindful of how changes will impact other parts of your application. Introducing AI to the mix, it's not difficult to see how a simple shift to storing code in this way can really introduce some very powerful ways of maintaining, developing and deploying your web applications.

Other benefits of this approach include:

- Increased portability of code
- Ease of delivery and backup
- Ability to enforce granular editing permissions
- The ability to override data in order to share the same code across applications and organizations

## What is stellisoft.com?

[stellisoft.com](https://stellisoft.com/) is the platform, namely the IDE, with which you author your applications using Stellify. It consists of:

- An Interface Builder
- A Code Editor
- A Configuration Editor
- A Bulk Application Editor

Using the Config Editor, you can connect to your own database to store your application data (code) giving you complete autonomy.

## About this Repository

This repo containes the a fully usable, stable version of the Laravel web application we're using to serve stellisoft.com. More specifically, it contains the methods that:

- Handle routing, accepting requests.
- Fetch data from a database containing Stellify data objects.
- Parse the data objects.
- Construct a response to return to the server.

It also includes convenience methods that allow you to convert your application's data back into raw code meaning, should you wish to stop using Stellify or simply want backups stored as files, you can do so.

NOTE: This repository doesn't include code for generating data. To achieve this, you can use our editor found at [stellisoft.com](https://stellisoft.com/).

## Documentation

### Development Setup

The easiest way to develop with Laravel right now is to install [Laravel Herd](https://herd.laravel.com/) on your machine and follow their documentation. Other means of developing and hosting Laravel applications are well documented on their website, blogs, YouTube and Laracasts.

### Generate PHP/JS files from the database

Should you wish to extract your code from the database into an actual file then you can do so by simply requesting one of the following routes depending upon the language in question:

- For PHP files use: http://localhost/php/{filename}
- For Javascript files use: http://localhost/js/{filename}

Simply pass the filename as a parameter to view the code in your browser window. Alternatively, you can add the query parameter ?file=true to download the file directly to your device.

## Contributing

Thank you for considering contributing to Stellify! The contribution guide can be found in the [Stellify documentation](https://stellisoft.com/documentation/contributions).

## Security Vulnerabilities

If you discover a security vulnerability within stellify, please send an e-mail to Matthew Anderson via [matthew.anderson@stellisoft.com](mailto:matthew.anderson@stellisoft.com). All security vulnerabilities will be promptly addressed.

## License

The stellify framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Learn more

If you would like to learn more about Stellify then you can read the comprehensive documentation on our website:

- [Configuring routes](https://stellisoft.com/documentation/routes).
- [Building interfaces with HTML and CSS](https://stellisoft.com/documentation/interface-builder).
- [Writing code](https://stellisoft.com/documentation/code-editor).
- [Configuring your application](https://stellisoft.com/documentation/configuration-editor).
- [Performing bulk operations](https://stellisoft.com/documentation/bulk-application-editor).
- [Working with built-in version control](https://stellisoft.com/documentation/version-control).
- [Supports popular web APIs](https://stellisoft.com/documentation/web-apis).
- [Access pre-baked functionality](https://stellisoft.com/documentation/stellify-services).
