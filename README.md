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

This backend has been written solely by me (Tamas Kenez) from
May 12 - May 26 (2019).
This is my first time I'm implementing web services and backend. Prior to this
implementation I have never worked with web technologies professionally and
have not studied them in school.

## Game play

Players have a balance which they can top-up and use for bets the game. When
they join a game a fixed amount ($1) will be deducted from their balance as
their bet. When the game finishes the winner gets all the players' bets. If
there's no winner (no smallest, unique number) the bets are lost.

One game round has a fixed number of players. This backend manages games with
3, 13 and 101 players. Players can join a game type (3, 13 or 101) anytime by
picking a number. They will be assigned to the current, ongoing game of the
chosen type. If there's no ongoing game, the a new one will be opened. One
player can participate in one type only once.

Whenever the sufficient number of players have joined a game the backend
determines the winner (if there's one) and transfers the prize to the winner's
balance.

## Choice of the problem

I chose this problem because it seemed to provide the following interesting
challenges:

- Managing simultaneous interacting requests (join-game requests) which
  require synchronization/locking.
- Identifying how the backend can be organized to provide scalability.
- Real-time data stream for the client (increasing number of players
  and announcing winner for a game the client has joined in)

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
  balance, also indexed by email.
- the `current_game` (PK: game_type_id) table contains only 3 lines for the 3
  types of games. It keeps track of the currently ongoing games' number of
  joined players and the game ids.
- the `game_picked_numbers` is a player - game-type relation, additionally
  storing the picked numbers themselves. It needs to answer these questions:
  + to determine the winning number, we need to now, which numbers are playing
    in a particular game
  + to determine if a player can join a game, we need to answer whether a player
    has already joined a game. For this reason, it's indexed by
    (game_type_id, player_id)
- the `game_history` (PK: game_id) stores the results (winner, winning number)
  of previous games. Also when a new game starts we already reserve a row here
  for the ongoing game so we have a valid game_id from the start.

I chose not to enforce foreign keys in the database, because the business logic
already enforces those and it would make DB operations slower.

#### Synchronization

Simultaneous request may modify player and game data. To prevent data corruption
we use row locking with the following rules:

- Any operation modifying player data must lock the player's row in `player`
  table.
- Any operation modifying data related to an ongoing game, must lock the row
  in `current_game`.
- Operations modifying both player and current game data lock first the `player`
  row then the `current_game` row.

The main place synchronization is used is the `join-player` request. We first
lock the player's row, validate the join request (if the player has sufficient
balance and not already participating in the game) then we lock the row in
`current_game`.

#### The join-game request

- First we check if the player has enough balance for a bet, by locking the
  player's row.
- We check if the player is participating in this game with a (game_type_id,
  player_id) search in `game_picked_numbers`.
- At his point we lock and retrieve information about the `current_game`.
- Record the picked number `game_picked_numbers` and update player's balance
  `player`, both rows are locked by us.
- If this is the first


## Missing features

- Scalability
- Logs
- Authentication
- Polling, real-time data stream
- Performance benchmarks
- Further optimization: less requests
