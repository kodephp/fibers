# Nova Fibers Project Summary

## Overview

Nova Fibers is a robust, framework-agnostic Fiber (纤程) client for PHP 8.1+, inspired by Swoole/Swow but built on native PHP Fibers with graceful fallbacks. It provides a comprehensive set of tools for managing fibers, including fiber pools, channels, event buses, context management, profiling, scheduling, and ORM integration.

## Key Features

1. **FiberPool**: High-performance fiber pool with automatic resource management
2. **Channel**: Communication mechanism between fibers
3. **EventBus**: Event-driven architecture support
4. **Context**: Context management system for passing data between fibers
5. **Profiler**: Performance monitoring and analysis tools
6. **Scheduler**: Distributed scheduler interface with local implementation
7. **ORM**: Fiber-aware ORM adapters (Eloquent/Fixtures)
8. **Support**: CPU info and environment detection utilities
9. **Task**: Task runner for executing tasks in fibers
10. **Attributes**: PHP 8.1 attributes for FiberSafe, Timeout, and ChannelListener
11. **Contracts**: Runnable interface for defining executable tasks
12. **Facades**: Simplified fiber usage through facades
13. **Commands**: Command-line application and commands for initialization and management
14. **Examples**: Comprehensive example files demonstrating various features
15. **Tests**: Full unit test coverage for all core functionality

## Project Structure

The project follows a modular structure with the following key directories:

- `src/`: Main source code
  - `Core/`: FiberPool implementation
  - `Channel/`: Channel communication mechanism
  - `Event/`: EventBus implementation
  - `Context/`: Context management system
  - `Profiler/`: Performance profiling tools
  - `Scheduler/`: Distributed scheduler interface and local implementation
  - `ORM/`: ORM adapters and fixtures
  - `Support/`: Utility classes for CPU info and environment detection
  - `Task/`: Task runner implementation
  - `Attributes/`: PHP 8.1 attributes
  - `Contracts/`: Interface definitions
  - `Facades/`: Facade implementations
  - `Commands/`: Command-line commands
- `tests/`: Unit tests
- `examples/`: Example files
- `config/`: Configuration templates
- `bin/`: Command-line executable

## Development Notes

- The project requires PHP 8.1 or higher
- All code follows PSR-12 coding standards
- Comprehensive unit tests are provided for all core functionality
- The project is designed to be framework-agnostic but includes specific support for Laravel and Symfony
- PHP 8.4 compatibility has been addressed with graceful fallbacks for fiber suspension in destructors

## Future Enhancements

1. Context variable passing between fibers
2. Distributed fiber scheduling across machines
3. Web-based profiler visualization panel
4. Seamless integration with Swoole/OpenSwoole/Swow/Workerman
5. Fiber-aware ORM implementations

## Getting Started

1. Install the package via Composer: `composer require nova/fibers`
2. Review the README.md for detailed usage instructions
3. Explore the example files in the `examples/` directory
4. Run the unit tests with `composer test`