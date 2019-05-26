# MinUniq


## Introduction

MinUniq is a simple game. Players submit a number (1-9999) and the winner is the
player with the smallest, unique number. If there's no such number, nobody wins.

This repository implements a backend implementing a web API to manage players
and conduct MinUniq games.

- The backend has been deployed to an [AWS EC2 instance](http://aws1-env.26uugfn4gs.us-east-1.elasticbeanstalk.com/).
- The [API documentation](https://documenter.getpostman.com/view/7506301/S1M3v5Q6?version=latest
) (Postman collection).

## About the author.

This backend has been written by me (Tamas Kenez), May 12 - May 26 (2019).
This is my first time I'm implementing web services and backend, I have no
professional experience with backends.

[My resume](https://docs.google.com/document/d/1oq_I7zguXm6MrAvXdvBuX1Cofk7kRXwxY2rI1a6eEcc/edit?usp=sharing)

## Game play

Players have a balance which they can top-up and use it for the bets in the
game. When they join a game a fixed amount ($1) will be deducted from their
balance. When the game finishes the winner gets all the other players' bets. If
there's no winner (no smallest, unique number) the bets are lost.

One game round has a fixed number of players. This backend conducts games with
3, 13 and 101 players. Players can join a game type (3, 13 or 101) anytime by
picking a number. They will be assigned to the current, ongoing game of the
chosen type. If there's no ongoing game, the a new one will be opened. A
player can bet in the same game only once.

Whenever the sufficient number of players have joined a game the backend
determines the winner (if there's one) and transfers the prize to the winner's
balance.

## Choice of the problem

I chose this problem because it seemed to provide the following interesting
challenges:

- Managing simultaneous interacting requests (join-game requests) which
  require synchronization/locking.
- Real-time data stream for the client (increasing number of players
  and announcing winner for a game the client has joined in)
- Identifying how the backend can be organized to provide scalability (this has
  not been implemented).

## Design choices

The backend is implemented in PHP and provides simple REST services.
A simple, minimal front-end is provided to demonstrate the functionality. The
solution itself focuses only on the backend.

I chose PHP because it seemed to be the most approachable technology
for me, without prior experience. During development I really missed the
benefits of the compiled languages so for my next work I'd probably choose
a statically typed, compiled language.

The database is MySql because the RDBMS data model fits well with the nature of
the data we need to store in game. It contains 4 tables:

- the `player` table (PK: player_id) contains the player's account data and
  balance, also indexed by google-user-id (from the authentication service).
- the `current_game` (PK: game_type_id) table contains only 3 lines for the 3
  types of games. It keeps track of the currently ongoing games' number of
  joined players and the game ids.
- the `game_picked_numbers` stores which player joined which game-type and which
  number did they pick. It needs to answer these questions:
  + to determine the winning number, we need to know, which numbers are playing
    in a particular game
  + to determine if a player can join a game, we need to answer whether a player
    has already joined a game. For this reason, it's indexed by
    (game_type_id, player_id)
- the `game_history` (PK: game_id) stores the results (winner, winning number)
  of previous games. Also when a new game starts we already reserve a row here
  for the ongoing game so we have a valid game_id from the start.

I chose not to enforce foreign keys in the database, because the business logic
already enforces those and it would make DB operations slower. In a more
sensitive application (bank) we would need the extra safety.

### Synchronization

Simultaneous request may modify player and game data. To prevent data corruption
we use row locking with the following rules:

- Any operation modifying player data must lock the player's row in `player`
  table.
- Any operation modifying data related to an ongoing game, must lock the row
  in `current_game`.
- Operations modifying both player and current game data lock first the `player`
  row then the `current_game` row to prevent deadlocks. This is used in the
  `join-game` request.

## Testing

The implementation provides a test suite with the following parts:

- Create, initialize new database from script and verify tables.
- Smoke-test of `register-player`/`top-up-balance`/`delete-player` API,
  verifying the result directly (in a white-box manner).
- Unit test of all API requests, testing all corner cases. Since certain request
  make sense only under certain circumstances, the unit tests are not isolated
  but are building on top of each other.
- Exhaustive test of the 3-player game (trying all combinations of picked
  numbers).
- Random tests of 3 the game types (fixed random seed)

## Missing features

- The real-time update of participants is implemented by long-polling. A better
  option would be websockets.
- Scalability: the backend is implemented as a single database. For scalability
  the `player` table could be partitioned based on the player's location. The
  `current_game` and `game_picked_numbers` tables could be extracted into a
  separate game server backend which could have multiple instances running.
  Players would be initially assigned to the same external game server. When
  the load increases more and more player would be moved out to a second, third,
  etc... instances.
- Performance benchmarks: a fixed number of random games would be played on
  the test server and time would be measured.
- Performance monitoring: the requests would measure the time it takes to
  server the them and notify the admin on anomalies.
- Logs: all transactions could be logged to a NoSql data store.
- Optimization: Using more complex requests could reduce the number
  of requests the frontend is calling.
- Non-API files and admin operations should be protected in the deployed backend.
- Unit testing should be done in an isolated way (instead of the current
  interleaved way) by supplying snapshots to the tests.
- Send email to participants when game finished.
- Google authentication tests are missing.
- Email addresses should not be stored. Instead: retrieve email on session
  start and store with session.
- Backend should not call Google Auth services in each request. Instead, use
  session-id to track which user is logged in.
