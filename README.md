<p align="center"><a href="https://stellisoft.com" target="_blank"><img src="https://raw.githubusercontent.com/Stellify-Software-Ltd/stellify/refs/heads/main/public/stellify_logo.svg" width="200" alt="Stellify Logo"></a></p>

## About Stellify

Stellify is a format that represents the code you write as data rows in a database. This data can be translated back to the code you originally authored so that it can be executed no differently to the way it is when stored in a file format.

The main benefit of storing code in this way is that it allows us to leverage the power of database queries to perform actions such as making updates to all applications using Stellify, remotely, in the same way you update your operating system, at the click of a button.

Other benefits include:

- Increased portability of code
- Ease of backup
- Granular editing permissions
- Code sharing

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

It also includes convenience methods that allow you to convert your application data (code) back into files containing your code should you wish to stop using Stellify or store backups as files.

The repo doesn't include code for generating data. As discussed above, this can be achieved (for free) using our editor which is found at [stellisoft.com](https://stellisoft.com/).

## Development Setup

As were developing a Laravel app, the easiest way to develop with Laravel right now is to install [Laravel Herd](https://herd.laravel.com/) on your machine. Other means of developing and hosting Laravel applications are well documented.

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
