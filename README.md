<p align="center"><a href="https://stellisoft.com" target="_blank"><img src="https://raw.githubusercontent.com/Stellify-Software-Ltd/stellify/refs/heads/main/public/stellify_logo.svg" width="200" alt="Stellify Logo"></a></p>

The current Laravel web application framework, setup for use with Stellify.

## About Stellify

Stellify is a system of representing the code as JSON data objects, then storing them in a database so they can be easily manipulated. These data objects can be translated back to the code you originally authored to be interpreted on a server or sent to a browser so that it can be rendered/ executed, as would ordinarily be the case.

The main benefit of storing code in this way is that it allows us to leverage the power of database queries to perform powerful actions, such as making updates to all applications built using Stellify, remotely.

Other benefits include:

- Increased portability of code
- Ease of backup
- Granular editing permissions
- The ability to share the same code across applications and organizations

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

It also includes convenience methods that allow you to convert your application's data (objects) back into code stored in files so that, should you wish to stop using Stellify or simply want backups stored as files, then you can do so.

NOTE: The repo doesn't include code for generating data objects (an editor). As discussed above, you can use our editor (for free) at [stellisoft.com](https://stellisoft.com/).

## Development Setup

As we're dealing with a Laravel app, the easiest way to develop with Laravel right now is to install [Laravel Herd](https://herd.laravel.com/) on your machine. Other means of developing and hosting Laravel applications are well documented on their website, YouTube and Laracasts.

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
